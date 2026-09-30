<?php

namespace App\Domain\Generation\SiteContent;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\SiteStyle;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Rédige les textes d'un site par IA, puis les place dans la structure du brouillon.
 *
 * La structure (pages, sections, photos) reste celle de DraftSpecFactory :
 * l'IA ne décide que des textes, vérifiés par ContentValidator.
 */
class SiteContentGenerator
{
    private const MAX_ATTEMPTS = 2;

    public function __construct(
        private AiManager $ai,
        private DraftSpecFactory $drafts,
        private ContentValidator $validator,
    ) {}

    /**
     * @return array{spec: array<string, mixed>, warnings: list<string>}
     *
     * @throws AiException
     */
    public function generate(Site $site, ?string $instructions = null): array
    {
        $skeleton = $this->drafts->make($site);
        $pageKeys = array_column($skeleton['pages'], 'key');
        $brief = $site->brief;

        $prompt = $this->prompt($site, $skeleton, $instructions);
        $problems = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = $this->ai->generate(
                'site_content',
                new AiRequest(
                    system: $this->systemPrompt(),
                    prompt: $problems === [] ? $prompt : $this->correctionPrompt($prompt, $problems),
                    schemaName: 'site_content',
                    schema: ContentSchema::make(),
                ),
                $site,
                ['attempt' => $attempt, 'instructions' => $instructions],
            );

            $result = $this->validator->validate($response->data, $brief, $pageKeys);
            $problems = $result['errors'];

            if ($problems === []) {
                return [
                    'spec' => $this->merge($skeleton, $response->data),
                    'warnings' => $result['warnings'],
                ];
            }
        }

        throw new AiException("Les textes proposés par l'IA ne respectent pas les règles :\n- ".implode("\n- ", $problems));
    }

    private function systemPrompt(): string
    {
        return File::get(base_path('prompts/'.config('ai.prompt_version').'/site_content.md'));
    }

    /**
     * @param  array<string, mixed>  $skeleton
     */
    private function prompt(Site $site, array $skeleton, ?string $instructions): string
    {
        $brief = $site->brief;
        $pages = collect($skeleton['pages'])->pluck('nav_label')->implode(', ');

        $facts = [
            'entreprise' => $brief['business_name'],
            'activite' => $brief['activity'],
            'description' => $brief['description'] ?? null,
            'services' => collect($brief['services'] ?? [])->map(fn (array $service): array => array_filter([
                'nom' => $service['name'] ?? null,
                'precisions' => $service['description'] ?? null,
            ]))->values()->all(),
            'ville_principale' => $brief['city'],
            'communes_desservies' => $brief['service_area'] ?? [],
            'rayon_intervention_km' => $brief['service_radius_km'] ?? null,
            'horaires' => $brief['opening_hours'] ?? [],
            'precision_horaires' => $brief['opening_hours_note'] ?? null,
            'telephone' => $brief['phone'] ?? null,
            'email' => $brief['email'] ?? null,
            'adresse' => array_filter($brief['address'] ?? []),
            'ambiance' => SiteStyle::tryFrom($brief['style'] ?? '')?->getLabel(),
            'consignes_de_redaction' => $brief['notes'] ?? null,
        ];

        $prompt = "Pages du site : {$pages}.\n";
        $prompt .= $site->plan->max_pages > 1
            ? "Site de plusieurs pages : l'accueil présente l'essentiel et renvoie vers les pages détaillées.\n"
            : "Site d'une seule page : tous les contenus sont sur l'accueil, les champs des autres pages seront ignorés sauf « services[].details », « about_page.paragraphs » et « contact_page.text ».\n";
        $prompt .= "\nInformations fournies par le client (seule source de faits autorisée) :\n";
        $prompt .= json_encode(array_filter($facts, fn ($value): bool => $value !== null && $value !== []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (filled($instructions)) {
            $prompt .= "\n\nConsignes supplémentaires de l'équipe pour cette version :\n".trim($instructions);
        }

        return $prompt;
    }

    /**
     * @param  list<string>  $problems
     */
    private function correctionPrompt(string $prompt, array $problems): string
    {
        return $prompt."\n\nUne première proposition a été refusée pour les raisons suivantes. Rédige une nouvelle version complète qui corrige ces points :\n- ".implode("\n- ", $problems);
    }

    /**
     * Place les textes de l'IA dans la structure du brouillon.
     *
     * @param  array<string, mixed>  $skeleton
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function merge(array $skeleton, array $content): array
    {
        $spec = $skeleton;
        $spec['generated_by'] = 'ai';
        $spec['suggestions'] = array_values(array_filter(array_map(trim(...), $content['suggestions'])));

        if (in_array($content['schema_type'], ContentSchema::schemaTypes(), true)) {
            $spec['site']['schema_type'] = $content['schema_type'];
        }

        $isSinglePage = count($spec['pages']) === 1;
        $pageContent = [
            'home' => $content['home'],
            'services' => $content['services_page'],
            'about' => $content['about_page'],
            'gallery' => $content['gallery_page'],
            'contact' => $content['contact_page'],
        ];

        foreach ($spec['pages'] as $pageIndex => $page) {
            $text = $pageContent[$page['key']] ?? null;

            if ($text === null) {
                continue;
            }

            $page['title'] = Str::limit(trim($text['title']), 70, '');
            $page['meta_description'] = Str::limit(trim($text['meta_description']), 170, '');

            $sections = [];

            foreach ($page['sections'] as $section) {
                $section = $this->fillSection($section, $page['key'], $content, $isSinglePage);

                if ($section['type'] === 'cta' || ($isSinglePage && $section['type'] === 'contact')) {
                    array_push($sections, ...$this->extraSections($content, $page['key'], $isSinglePage));
                }

                if ($section['type'] === 'services' && $page['key'] === 'home' && ! $isSinglePage) {
                    $sections[] = $section;
                    array_push($sections, ...$this->highlights($content));

                    continue;
                }

                $sections[] = $section;
            }

            if ($isSinglePage) {
                $sections = $this->insertAfter($sections, 'services', $this->highlights($content));
            }

            $page['sections'] = $sections;
            $spec['pages'][$pageIndex] = $page;
        }

        return $spec;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function fillSection(array $section, string $pageKey, array $content, bool $isSinglePage): array
    {
        $home = $content['home'];
        $pageText = [
            'services' => $content['services_page'],
            'about' => $content['about_page'],
            'gallery' => $content['gallery_page'],
            'contact' => $content['contact_page'],
        ][$pageKey] ?? null;

        switch ($section['type']) {
            case 'hero':
                $section['h1'] = trim($home['h1']);
                $section['lead'] = trim($home['lead']);
                break;

            case 'page_header':
                $section['h1'] = trim($pageText['h1']);
                $section['lead'] = trim($pageText['lead']) ?: null;
                break;

            case 'services':
                $detailed = ($section['layout'] ?? 'cards') === 'detailed';

                if ($pageKey === 'home') {
                    $section['heading'] = trim($home['services_heading']);
                    $section['intro'] = trim($home['services_intro']) ?: null;
                }

                foreach ($section['items'] as $index => $item) {
                    $generated = $content['services'][$index] ?? null;

                    if ($generated !== null) {
                        $section['items'][$index]['text'] = trim($detailed ? $generated['details'] : $generated['summary']);
                    }
                }
                break;

            case 'about':
                $section['heading'] = $pageKey === 'home' ? trim($home['about_heading']) : $section['heading'];
                $paragraphs = $pageKey === 'home' && ! $isSinglePage ? $home['about_paragraphs'] : $content['about_page']['paragraphs'];
                $section['paragraphs'] = array_values(array_filter(array_map(trim(...), $paragraphs)));
                break;

            case 'gallery':
                $section['intro'] = $pageKey === 'gallery' ? (trim($content['gallery_page']['intro']) ?: null) : $section['intro'];
                break;

            case 'zone':
                $section['heading'] = trim($home['zone_heading']);
                $section['text'] = trim($home['zone_text']);
                break;

            case 'cta':
                $section['heading'] = trim($home['cta_heading']);
                $section['text'] = trim($home['cta_text']);
                break;

            case 'contact':
                $section['text'] = trim($content['contact_page']['text']);
                break;
        }

        return $section;
    }

    /**
     * FAQ, placée avant l'appel à l'action de l'accueil (ou avant le contact en une page).
     *
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function extraSections(array $content, string $pageKey, bool $isSinglePage): array
    {
        $items = array_values(array_filter($content['faq']['items'], fn (array $item): bool => filled($item['question']) && filled($item['answer'])));

        if ($pageKey !== 'home' || count($items) < 2) {
            return [];
        }

        return [[
            'type' => 'faq',
            'anchor' => $isSinglePage ? 'faq' : null,
            'heading' => trim($content['faq']['heading']) ?: 'Questions fréquentes',
            'items' => array_map(fn (array $item): array => ['question' => trim($item['question']), 'answer' => trim($item['answer'])], $items),
        ]];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function highlights(array $content): array
    {
        $items = array_values(array_filter($content['highlights']['items'], fn (array $item): bool => filled($item['title'])));

        if (count($items) < 2) {
            return [];
        }

        return [[
            'type' => 'highlights',
            'heading' => trim($content['highlights']['heading']) ?: 'Nos points forts',
            'items' => array_map(fn (array $item): array => ['title' => trim($item['title']), 'text' => trim($item['text'])], $items),
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    private function insertAfter(array $sections, string $type, array $extra): array
    {
        $position = collect($sections)->search(fn (array $section): bool => $section['type'] === $type);

        if ($position === false || $extra === []) {
            return $sections;
        }

        array_splice($sections, $position + 1, 0, $extra);

        return $sections;
    }
}
