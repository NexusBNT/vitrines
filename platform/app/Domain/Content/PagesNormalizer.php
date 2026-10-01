<?php

namespace App\Domain\Content;

use App\Domain\Sites\Design;
use App\Enums\MediaSource;
use App\Enums\PlanFeature;
use App\Models\Site;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie et nettoie les pages envoyées par l'éditeur avant de les enregistrer dans la spécification.
 *
 * Erreurs de structure (arborescence, URL, nombre de pages, types de sections) : refus de l'enregistrement.
 * Contenu incomplet (titre vide, élément sans nom…) : accepté, signalé dans les avertissements,
 * pour que l'enregistrement automatique ne bloque jamais la saisie.
 *
 * Structure d'une page : { key, slug (chemin complet), parent, nav_label, in_nav, title, meta_description, noindex, sections[] }.
 */
class PagesNormalizer
{
    /**
     * Pages présentes sur tous les sites, qui ne peuvent pas être supprimées.
     */
    public const PROTECTED_PAGES = ['home', 'contact'];

    /**
     * Chemins réservés aux pages automatiques et aux fichiers du site.
     */
    public const RESERVED_SLUGS = ['mentions-legales', 'confidentialite', 'merci', 'not-found', '404', 'assets', 'media', 'sitemap', 'robots'];

    public const HEAD_SECTIONS = ['hero', 'page_header'];

    private const MAX_SECTIONS = 40;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  mixed  $pages  Pages reçues de l'éditeur
     * @param  list<string>  $previousKeys  Pages de la version précédente (les pages protégées doivent y rester)
     * @return array{pages: list<array<string, mixed>>, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public function normalize(mixed $pages, Site $site, array $previousKeys = []): array
    {
        $this->warnings = [];

        if (! is_array($pages) || $pages === []) {
            $this->fail('Le site doit contenir au moins la page d\'accueil.');
        }

        $pages = array_values(array_filter($pages, 'is_array'));
        $maxPages = max(1, (int) $site->plan->max_pages);

        if (count($pages) > $maxPages) {
            $this->fail($maxPages === 1
                ? 'L\'offre de ce site ne comprend qu\'une seule page.'
                : "L'offre de ce site est limitée à {$maxPages} pages.");
        }

        $keys = array_map(fn (array $page): string => (string) ($page['key'] ?? ''), $pages);

        foreach ($keys as $key) {
            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $key)) {
                $this->fail('Identifiant de page invalide.');
            }
        }

        if (count($keys) !== count(array_unique($keys))) {
            $this->fail('Deux pages ont le même identifiant.');
        }

        if (($pages[0]['key'] ?? null) !== 'home') {
            $this->fail('La page d\'accueil doit rester la première page.');
        }

        foreach (self::PROTECTED_PAGES as $protected) {
            if (in_array($protected, $previousKeys, true) && ! in_array($protected, $keys, true)) {
                $this->fail($protected === 'home' ? 'La page d\'accueil ne peut pas être supprimée.' : 'La page Contact ne peut pas être supprimée.');
            }
        }

        $mediaIds = $site->media()->pluck('id')->all();
        $aiMediaIds = $site->media()->where('source', MediaSource::Ai)->pluck('id')->all();
        $context = [
            'keys' => $keys,
            'media' => $mediaIds,
            'ai_media' => $aiMediaIds,
            'gallery' => $site->plan->hasFeature(PlanFeature::Gallery),
            'single_page' => $maxPages === 1,
        ];

        $byKey = [];

        foreach ($pages as $page) {
            $byKey[$page['key']] = $this->page($page, $context);
        }

        return ['pages' => $this->ordered($byKey), 'warnings' => $this->warnings];
    }

    /**
     * Avertissements d'une spécification déjà enregistrée (affichés à l'ouverture de l'éditeur).
     *
     * @param  list<array<string, mixed>>  $pages
     * @return list<string>
     */
    public function warningsFor(array $pages, Site $site): array
    {
        try {
            return $this->normalize(PageTree::toEditor($pages), $site)['warnings'];
        } catch (ValidationException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function page(array $page, array $context): array
    {
        $key = $page['key'];
        $isHome = $key === 'home';
        $navLabel = $this->string($page['nav_label'] ?? null, 30) ?? ($isHome ? 'Accueil' : 'Page');
        $label = "« {$navLabel} »";

        $parent = $isHome ? null : ($page['parent'] ?? null);

        if ($parent !== null && (! in_array($parent, $context['keys'], true) || $parent === $key || $parent === 'home')) {
            $this->fail("La page {$label} est rangée sous une page introuvable.");
        }

        $segment = $isHome ? '' : (Str::slug((string) ($page['segment'] ?? '')) ?: Str::slug($navLabel));
        $segment = mb_substr($segment, 0, 60);

        if (! $isHome && $segment === '') {
            $this->fail("L'adresse de la page {$label} est vide.");
        }

        if (! $isHome && $parent === null && in_array($segment, self::RESERVED_SLUGS, true)) {
            $this->fail("L'adresse « /{$segment}/ » est réservée : choisissez-en une autre pour la page {$label}.");
        }

        $title = $this->string($page['title'] ?? null, 70);
        $description = $this->string($page['meta_description'] ?? null, 170);

        if ($title === null) {
            $this->warnings[] = "Page {$label} : le titre pour Google est vide.";
        }

        if ($description === null) {
            $this->warnings[] = "Page {$label} : la meta description est vide.";
        }

        return [
            'key' => $key,
            'segment' => $segment,
            'parent' => $parent,
            'nav_label' => $navLabel,
            'in_nav' => $isHome ? false : ($page['in_nav'] ?? true) !== false,
            'title' => $title ?? '',
            'meta_description' => $description ?? '',
            'noindex' => ! $isHome && ($page['noindex'] ?? false) === true,
            'sections' => $this->sections($page['sections'] ?? [], $context, $label),
        ];
    }

    /**
     * Pages dans l'ordre du menu (chaque page suivie de ses sous-pages) avec leur chemin complet.
     *
     * @param  array<string, array<string, mixed>>  $byKey
     * @return list<array<string, mixed>>
     */
    private function ordered(array $byKey): array
    {
        $ordered = [];
        $slugs = [];

        foreach ($byKey as $key => $page) {
            if ($page['parent'] === null) {
                $ordered[] = $page;

                foreach ($byKey as $child) {
                    if ($child['parent'] === $key) {
                        $ordered[] = $child;
                    }
                }
            } elseif ($byKey[$page['parent']]['parent'] !== null) {
                $this->fail("La page « {$page['nav_label']} » est rangée trop profondément : deux niveaux au maximum.");
            }
        }

        foreach ($ordered as $index => $page) {
            $slug = $page['key'] === 'home' ? '' : ($page['parent'] === null ? $page['segment'] : $byKey[$page['parent']]['segment'].'/'.$page['segment']);

            if (isset($slugs[$slug])) {
                $this->fail("Deux pages ont la même adresse : /{$slug}/.");
            }

            $slugs[$slug] = true;
            unset($page['segment']);
            $ordered[$index] = ['key' => $page['key'], 'slug' => $slug, ...collect($page)->except('key')->all()];
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function sections(mixed $sections, array $context, string $label): array
    {
        if (! is_array($sections) || $sections === []) {
            $this->fail("La page {$label} doit commencer par un en-tête.");
        }

        $sections = array_slice(array_values(array_filter($sections, 'is_array')), 0, self::MAX_SECTIONS);
        $clean = [];

        foreach ($sections as $index => $section) {
            $type = $section['type'] ?? null;
            $isHead = in_array($type, self::HEAD_SECTIONS, true);

            if ($isHead !== ($index === 0)) {
                $this->fail("La page {$label} doit commencer par un seul en-tête (bandeau ou titre de page).");
            }

            if (! in_array($type, SectionTypes::all(), true)) {
                $this->fail("Type de section inconnu sur la page {$label}.");
            }

            if ($type === 'gallery' && ! $context['gallery']) {
                $this->fail('La galerie n\'est pas comprise dans l\'offre de ce site.');
            }

            $section = $this->section($section, $context, $label);

            if ($section !== null) {
                $clean[] = $section;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function section(array $section, array $context, string $label): ?array
    {
        $media = fn (mixed $id): ?int => in_array((int) $id, $context['media'], true) ? (int) $id : null;

        $clean = match ($section['type']) {
            'hero' => [
                'h1' => $this->required($section['h1'] ?? null, 90, "Page {$label} : le titre principal (H1) est vide."),
                'lead' => $this->string($section['lead'] ?? null, 300),
                'image' => $media($section['image'] ?? null),
                'variant' => array_key_exists($section['variant'] ?? '', Design::STRUCTURE['hero_layout']) ? $section['variant'] : 'split',
                'cta_label' => $this->string($section['cta_label'] ?? null, 40),
            ],
            'page_header' => [
                'h1' => $this->required($section['h1'] ?? null, 90, "Page {$label} : le titre principal (H1) est vide."),
                'lead' => $this->string($section['lead'] ?? null, 300),
            ],
            'services' => [
                ...$this->heading($section),
                'layout' => ($section['layout'] ?? null) === 'detailed' ? 'detailed' : 'cards',
                'items' => $this->items($section['items'] ?? [], 30, fn (array $item): ?array => ($name = $this->string($item['name'] ?? null, 120)) === null ? null : [
                    'name' => $name,
                    'text' => $this->string($item['text'] ?? null, 2000),
                    'image' => $media($item['image'] ?? null),
                ]),
                'link' => $this->link($section['link'] ?? null, $context),
            ],
            'about' => [
                'heading' => $this->string($section['heading'] ?? null, 120),
                'paragraphs' => array_slice(array_values(array_filter(array_map(fn (mixed $paragraph): ?string => $this->string($paragraph, 2000), is_array($section['paragraphs'] ?? null) ? $section['paragraphs'] : []))), 0, 20),
                'image' => $media($section['image'] ?? null),
                'link' => $this->link($section['link'] ?? null, $context),
            ],
            'highlights' => [
                'heading' => $this->string($section['heading'] ?? null, 120),
                'items' => $this->items($section['items'] ?? [], 12, fn (array $item): ?array => ($title = $this->string($item['title'] ?? null, 120)) === null ? null : [
                    'title' => $title,
                    'text' => $this->string($item['text'] ?? null, 500),
                ]),
            ],
            'gallery' => [
                ...$this->heading($section),
                'images' => array_slice(array_values(array_unique(array_filter(
                    array_map($media, is_array($section['images'] ?? null) ? $section['images'] : []),
                    fn (?int $id): bool => $id !== null && ! in_array($id, $context['ai_media'], true),
                ))), 0, 60),
                'link' => $this->link($section['link'] ?? null, $context),
            ],
            'zone' => [
                'heading' => $this->string($section['heading'] ?? null, 120),
                'text' => $this->string($section['text'] ?? null, 1000) ?? '',
                'towns' => array_slice(array_values(array_unique(array_filter(array_map(fn (mixed $town): ?string => $this->string($town, 60), is_array($section['towns'] ?? null) ? $section['towns'] : [])))), 0, 60),
            ],
            'faq' => [
                'heading' => $this->string($section['heading'] ?? null, 120),
                'items' => $this->items($section['items'] ?? [], 30, function (array $item): ?array {
                    $question = $this->string($item['question'] ?? null, 200);
                    $answer = $this->string($item['answer'] ?? null, 1500);

                    return $question === null || $answer === null ? null : ['question' => $question, 'answer' => $answer];
                }),
            ],
            'cta' => [
                'heading' => $this->required($section['heading'] ?? null, 120, "Page {$label} : l'appel à l'action n'a pas de titre."),
                'text' => $this->string($section['text'] ?? null, 300),
            ],
            'contact' => [
                'heading' => $this->string($section['heading'] ?? null, 120),
                'text' => $this->string($section['text'] ?? null, 500),
                'show_map' => ($section['show_map'] ?? false) === true,
            ],
            'content' => [
                'blocks' => $this->trimEmptyParagraphs((new RichText($context['media']))->sanitize($section['blocks'] ?? [])),
            ],
        };

        if ($section['type'] === 'content' && $clean['blocks'] === []) {
            return null;
        }

        foreach ($this->missingLinks($section['type'], $clean, $context) as $missing) {
            $this->warnings[] = "Page {$label} : un lien vise une page supprimée ({$missing}).";
        }

        $anchor = Str::slug((string) ($section['anchor'] ?? ''));
        $navLabel = $this->string($section['nav_label'] ?? null, 30);

        return [
            'type' => $section['type'],
            ...($anchor !== '' ? ['anchor' => mb_substr($anchor, 0, 40)] : []),
            ...($navLabel !== null && $anchor !== '' ? ['nav_label' => $navLabel] : []),
            ...$clean,
        ];
    }

    /**
     * Retire les paragraphes vides en début et en fin de bloc (lignes laissées par la saisie).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function trimEmptyParagraphs(array $blocks): array
    {
        $isEmpty = fn (?array $block): bool => $block !== null && $block['type'] === 'paragraph' && $block['content'] === [];

        while ($isEmpty($blocks[0] ?? null)) {
            array_shift($blocks);
        }

        while ($isEmpty($blocks[count($blocks) - 1] ?? null)) {
            array_pop($blocks);
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array{heading: ?string, intro: ?string}
     */
    private function heading(array $section): array
    {
        return [
            'heading' => $this->string($section['heading'] ?? null, 120),
            'intro' => $this->string($section['intro'] ?? null, 500),
        ];
    }

    /**
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function items(mixed $items, int $max, callable $map): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_slice(array_values(array_filter(array_map(fn (mixed $item): ?array => is_array($item) ? $map($item) : null, $items))), 0, $max);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{page: string, label: string}|null
     */
    private function link(mixed $link, array $context): ?array
    {
        if (! is_array($link) || ! in_array($link['page'] ?? null, $context['keys'], true)) {
            return null;
        }

        return ['page' => $link['page'], 'label' => $this->string($link['label'] ?? null, 60) ?? 'En savoir plus'];
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function missingLinks(string $type, array $section, array $context): array
    {
        if ($type !== 'content') {
            return [];
        }

        return array_values(array_diff(RichText::linkedPages($section['blocks']), $context['keys']));
    }

    private function required(mixed $value, int $max, string $warning): string
    {
        $value = $this->string($value, $max);

        if ($value === null) {
            $this->warnings[] = $warning;
        }

        return $value ?? '';
    }

    private function string(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], (string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['pages' => $message]);
    }
}
