<?php

namespace App\Domain\Sites;

use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Enums\PlanFeature;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Construit une spécification de site complète à partir du brief, sans IA.
 *
 * Les textes sont volontairement factuels : ils ne reprennent que ce qui a été saisi.
 * L'IA (lot 4) remplacera ces textes en gardant la même structure.
 *
 * Structure produite :
 * - site : données de l'entreprise normalisées (nom, activité, type Schema.org, coordonnées, horaires, zone) ;
 * - theme : nom du thème, ambiance, couleurs ;
 * - pages : liste ordonnée de pages { key, slug, nav_label, title, meta_description, sections[] }.
 *   Chaque page commence par une section « hero » ou « page_header » qui porte le seul H1.
 */
class DraftSpecFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(Site $site): array
    {
        $brief = $site->brief;
        $media = $site->media()->where('status', MediaStatus::Ready)->get();
        $multiPage = $site->plan->max_pages > 1;
        $withGallery = $site->plan->hasFeature(PlanFeature::Gallery);

        $siteData = $this->siteData($brief);
        $context = [
            'name' => $siteData['name'],
            'activity' => $siteData['activity'],
            'city' => $siteData['city'],
        ];

        $pages = $multiPage
            ? $this->multiPage($siteData, $context, $media, $withGallery)
            : [$this->singlePage($siteData, $context, $media)];

        return [
            'version' => 1,
            'generated_by' => 'draft',
            'site' => $siteData,
            'theme' => [
                'name' => $site->theme,
                'style' => $brief['style'] ?? 'moderne',
                'colors' => [
                    'primary' => $brief['colors']['primary'] ?? '#1d4ed8',
                    'secondary' => $brief['colors']['secondary'] ?? null,
                ],
            ],
            'pages' => $pages,
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    private function siteData(array $brief): array
    {
        return [
            'name' => trim($brief['business_name']),
            'activity' => trim($brief['activity']),
            'schema_type' => $this->schemaType($brief['activity']),
            'description' => $this->firstSentence($brief['description'] ?? '') ?? '',
            'full_description' => trim($brief['description'] ?? ''),
            'services' => collect($brief['services'] ?? [])
                ->filter(fn (array $service): bool => filled($service['name'] ?? null))
                ->map(fn (array $service): array => ['name' => trim($service['name']), 'text' => filled($service['description'] ?? null) ? trim($service['description']) : null])
                ->values()
                ->all(),
            'phone' => $brief['phone'] ?? null,
            'email' => $brief['email'] ?? null,
            'address' => [
                'street' => $brief['address']['street'] ?? null,
                'postal_code' => $brief['address']['postal_code'] ?? null,
                'city' => $brief['address']['city'] ?? null,
            ],
            'city' => trim($brief['city']),
            'service_area' => array_values(array_filter($brief['service_area'] ?? [])),
            'service_radius_km' => isset($brief['service_radius_km']) && $brief['service_radius_km'] !== '' ? (int) $brief['service_radius_km'] : null,
            'opening_hours' => array_values($brief['opening_hours'] ?? []),
            'opening_hours_note' => $brief['opening_hours_note'] ?? null,
            'socials' => array_filter($brief['socials'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array{name: string, activity: string, city: string}  $context
     * @param  Collection<int, Media>  $media
     * @return list<array<string, mixed>>
     */
    private function multiPage(array $site, array $context, Collection $media, bool $withGallery): array
    {
        $works = $this->mediaIds($media, MediaCategory::Work);
        $showGallery = $withGallery && $works !== [];
        $services = $this->serviceItems($site);

        $home = [
            $this->hero($context, $media),
            [
                'type' => 'services',
                'layout' => 'cards',
                'heading' => 'Nos services',
                'intro' => null,
                'items' => $services,
                'link' => ['page' => 'services', 'label' => 'Voir le détail des services'],
            ],
            [
                'type' => 'about',
                'heading' => 'À propos de '.$context['name'],
                'paragraphs' => array_slice($this->paragraphs($site), 0, 1),
                'image' => $this->firstMediaId($media, [MediaCategory::Team, MediaCategory::Premises]),
                'link' => ['page' => 'about', 'label' => 'En savoir plus'],
            ],
        ];

        if ($showGallery) {
            $home[] = [
                'type' => 'gallery',
                'heading' => 'Nos réalisations',
                'intro' => null,
                'images' => array_slice($works, 0, 6),
                'link' => ['page' => 'gallery', 'label' => 'Voir toutes les réalisations'],
            ];
        }

        $home[] = $this->zone($site);
        $home[] = $this->cta($context);

        $pages = [
            $this->page('home', '', 'Accueil', $context['name'].' – '.$context['activity'].' à '.$context['city'], $this->homeDescription($site), $home),
            $this->page('services', 'services', 'Services', 'Services de '.Str::lower($context['activity']).' à '.$context['city'].' – '.$context['name'], $this->servicesDescription($site), [
                $this->pageHeader('Nos services de '.Str::lower($context['activity']).' à '.$context['city'], null),
                ['type' => 'services', 'layout' => 'detailed', 'heading' => null, 'intro' => null, 'items' => $services, 'link' => null],
                $this->cta($context),
            ]),
            $this->page('about', 'a-propos', 'À propos', 'À propos – '.$context['name'].', '.Str::lower($context['activity']).' à '.$context['city'], $this->homeDescription($site), [
                $this->pageHeader('À propos de '.$context['name'], null),
                [
                    'type' => 'about',
                    'heading' => null,
                    'paragraphs' => $this->paragraphs($site),
                    'image' => $this->firstMediaId($media, [MediaCategory::Team, MediaCategory::Premises]),
                    'link' => null,
                ],
                $this->zone($site),
            ]),
        ];

        if ($showGallery) {
            $pages[] = $this->page('gallery', 'realisations', 'Réalisations', 'Réalisations – '.$context['name'].', '.Str::lower($context['activity']).' à '.$context['city'], 'Découvrez en images des réalisations de '.$context['name'].', '.Str::lower($context['activity']).' à '.$context['city'].'.', [
                $this->pageHeader('Nos réalisations', null),
                ['type' => 'gallery', 'heading' => null, 'intro' => null, 'images' => $works, 'link' => null],
                $this->cta($context),
            ]);
        }

        $pages[] = $this->page('contact', 'contact', 'Contact', 'Contact – '.$context['name'].', '.Str::lower($context['activity']).' à '.$context['city'], 'Contactez '.$context['name'].', '.Str::lower($context['activity']).' à '.$context['city'].' : téléphone, email, horaires et formulaire de contact.', [
            $this->pageHeader('Contacter '.$context['name'], null),
            $this->contact(),
        ]);

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array{name: string, activity: string, city: string}  $context
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>
     */
    private function singlePage(array $site, array $context, Collection $media): array
    {
        return $this->page('home', '', 'Accueil', $context['name'].' – '.$context['activity'].' à '.$context['city'], $this->homeDescription($site), [
            $this->hero($context, $media),
            ['type' => 'services', 'layout' => 'detailed', 'anchor' => 'services', 'nav_label' => 'Services', 'heading' => 'Nos services', 'intro' => null, 'items' => $this->serviceItems($site), 'link' => null],
            [
                'type' => 'about',
                'anchor' => 'a-propos',
                'nav_label' => 'À propos',
                'heading' => 'À propos de '.$context['name'],
                'paragraphs' => $this->paragraphs($site),
                'image' => $this->firstMediaId($media, [MediaCategory::Team, MediaCategory::Premises]),
                'link' => null,
            ],
            $this->zone($site),
            [...$this->contact(), 'anchor' => 'contact', 'nav_label' => 'Contact', 'heading' => 'Contact'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function page(string $key, string $slug, string $navLabel, string $title, string $description, array $sections): array
    {
        return [
            'key' => $key,
            'slug' => $slug,
            'nav_label' => $navLabel,
            'title' => Str::limit($title, 65, '…'),
            'meta_description' => Str::limit($description, 158, '…'),
            'sections' => $sections,
        ];
    }

    /**
     * @param  array{name: string, activity: string, city: string}  $context
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>
     */
    private function hero(array $context, Collection $media): array
    {
        $image = $this->firstMediaId($media, [MediaCategory::Hero, MediaCategory::Work, MediaCategory::Premises]);

        return [
            'type' => 'hero',
            'variant' => $image === null ? 'plain' : 'split',
            'h1' => $context['activity'].' à '.$context['city'],
            'lead' => $context['name'].', votre '.Str::lower($context['activity']).' à '.$context['city'].' et ses environs.',
            'image' => $image,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageHeader(string $h1, ?string $lead): array
    {
        return ['type' => 'page_header', 'h1' => $h1, 'lead' => $lead];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function zone(array $site): array
    {
        $text = 'Nous intervenons à '.$site['city'];
        $text .= $site['service_radius_km'] !== null ? ' et dans un rayon de '.$site['service_radius_km'].' km.' : ' et ses environs.';

        return [
            'type' => 'zone',
            'heading' => 'Zone d\'intervention',
            'text' => $text,
            'towns' => $site['service_area'],
        ];
    }

    /**
     * @param  array{name: string, activity: string, city: string}  $context
     * @return array<string, mixed>
     */
    private function cta(array $context): array
    {
        return [
            'type' => 'cta',
            'heading' => 'Un projet ? Parlons-en.',
            'text' => 'Contactez '.$context['name'].' pour discuter de votre besoin.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(): array
    {
        return [
            'type' => 'contact',
            'heading' => null,
            'text' => 'Décrivez votre besoin, nous vous répondons rapidement.',
            'show_map' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return list<array{name: string, text: ?string}>
     */
    private function serviceItems(array $site): array
    {
        return $site['services'];
    }

    /**
     * Découpe la description saisie en paragraphes (lignes vides ou retours à la ligne).
     *
     * @param  array<string, mixed>  $site
     * @return list<string>
     */
    private function paragraphs(array $site): array
    {
        $paragraphs = preg_split('/\R+/u', trim($site['full_description'])) ?: [];

        return array_values(array_filter(array_map(trim(...), $paragraphs)));
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function homeDescription(array $site): string
    {
        $services = collect($site['services'])->pluck('name')->take(3)->implode(', ');

        return sprintf(
            '%s, %s à %s. %s',
            $site['name'],
            Str::lower($site['activity']),
            $site['city'],
            $services !== '' ? $services.'. Contactez-nous.' : 'Contactez-nous.',
        );
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function servicesDescription(array $site): string
    {
        $services = collect($site['services'])->pluck('name')->implode(', ');

        return sprintf('%s à %s et environs : %s.', $site['name'], $site['city'], Str::lcfirst($services));
    }

    private function schemaType(string $activity): string
    {
        $normalized = Str::lower(Str::ascii($activity));

        foreach (config('vitrines.schema_types') as $keyword => $type) {
            if (str_contains($normalized, $keyword)) {
                return $type;
            }
        }

        return 'LocalBusiness';
    }

    private function firstSentence(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return null;
        }

        return preg_match('/^(.+?[.!?])(\s|$)/u', $text, $matches) ? $matches[1] : $text;
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return list<int>
     */
    private function mediaIds(Collection $media, MediaCategory $category): array
    {
        return $media->where('category', $category)->pluck('id')->values()->all();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @param  list<MediaCategory>  $categories
     */
    private function firstMediaId(Collection $media, array $categories): ?int
    {
        foreach ($categories as $category) {
            $found = $media->firstWhere('category', $category);

            if ($found !== null) {
                return $found->id;
            }
        }

        return null;
    }
}
