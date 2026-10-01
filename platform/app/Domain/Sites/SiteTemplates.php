<?php

namespace App\Domain\Sites;

/**
 * Thèmes de site prêts à l'emploi : chacun combine une structure (navigation, bandeau, agencement
 * des sections, pied de page), un style, une palette par défaut et une direction photographique
 * pour les illustrations générées.
 *
 * Ils s'inspirent des thèmes WordPress les plus installés (Astra, Kadence, GeneratePress, Avada,
 * Twenty Twenty-Four, OceanWP, Neve, Hestia, Blocksy…) sans en reprendre le code ni le nom.
 * L'IA part d'un thème puis peut en ajuster la structure, le style et les couleurs.
 */
final class SiteTemplates
{
    /**
     * @var array<string, array{label: string, inspiration: string, description: string, ideal_for: string, primary: string, secondary: string, image_style: string, structure: array<string, string>, style: array<string, string>}>
     */
    public const ALL = [
        'atelier' => [
            'label' => 'Atelier',
            'inspiration' => 'Astra (Local Business)',
            'description' => 'Classique et rassurant : barre d\'infos, menu en haut, photo à côté du titre, cartes de services.',
            'ideal_for' => 'plombiers, électriciens, menuisiers, dépannage, artisans du bâtiment',
            'primary' => '#2563eb',
            'secondary' => '#0f172a',
            'image_style' => 'warm natural daylight, honest materials and well-kept tools, workshop textures, slightly warm color grading',
            'structure' => ['nav_layout' => 'top', 'topbar' => 'infos', 'header_overlay' => 'no', 'hero_layout' => 'split', 'hero_align' => 'left', 'services_style' => 'cards', 'about_layout' => 'split', 'highlights_style' => 'columns', 'gallery_style' => 'grid', 'cta_style' => 'band', 'footer_layout' => 'columns', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'artisanal', 'radius' => 'medium', 'buttons' => 'rounded', 'shadow' => 'soft', 'header' => 'light', 'hero_background' => 'gradient', 'surface' => 'white', 'cards' => 'accent', 'section_alt' => 'tint', 'titles' => 'left', 'footer' => 'dark', 'headings' => 'normal', 'density' => 'comfortable'],
        ],
        'horizon' => [
            'label' => 'Horizon',
            'inspiration' => 'Kadence',
            'description' => 'Moderne et lumineux : menu transparent sur une grande photo, titre centré, services en tuiles photo.',
            'ideal_for' => 'services aux particuliers, dépannage informatique, coachs, auto-écoles, agences',
            'primary' => '#4f46e5',
            'secondary' => '#1e1b4b',
            'image_style' => 'bright, airy and optimistic mood, soft daylight, clean contemporary spaces, crisp and uncluttered composition',
            'structure' => ['nav_layout' => 'top', 'topbar' => 'none', 'header_overlay' => 'yes', 'hero_layout' => 'image', 'hero_align' => 'center', 'services_style' => 'tiles', 'about_layout' => 'overlap', 'highlights_style' => 'band', 'gallery_style' => 'masonry', 'cta_style' => 'card', 'footer_layout' => 'centered', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'moderne', 'radius' => 'large', 'buttons' => 'pill', 'shadow' => 'soft', 'header' => 'light', 'hero_background' => 'gradient', 'surface' => 'white', 'cards' => 'elevated', 'section_alt' => 'tint', 'titles' => 'centered', 'footer' => 'dark', 'headings' => 'normal', 'density' => 'airy'],
        ],
        'epure' => [
            'label' => 'Épure',
            'inspiration' => 'GeneratePress',
            'description' => 'Minimaliste : menu latéral à droite, bandeau texte seul, services numérotés, pied de page d\'une ligne.',
            'ideal_for' => 'consultants, professions libérales, formateurs, diagnostiqueurs, indépendants',
            'primary' => '#0f766e',
            'secondary' => '#134e4a',
            'image_style' => 'minimal and calm composition, generous negative space, neutral tones, soft diffuse light',
            'structure' => ['nav_layout' => 'sidebar_right', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_layout' => 'plain', 'hero_align' => 'left', 'services_style' => 'numbered', 'about_layout' => 'banner', 'highlights_style' => 'columns', 'gallery_style' => 'grid', 'cta_style' => 'minimal', 'footer_layout' => 'minimal', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'system', 'radius' => 'small', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'light', 'hero_background' => 'tint', 'surface' => 'white', 'cards' => 'bordered', 'section_alt' => 'neutral', 'titles' => 'accent', 'footer' => 'light', 'headings' => 'normal', 'density' => 'comfortable'],
        ],
        'prestige' => [
            'label' => 'Prestige',
            'inspiration' => 'Avada (luxe)',
            'description' => 'Haut de gamme : logo centré, photo plein écran au texte encadré, services en lignes élégantes.',
            'ideal_for' => 'architectes d\'intérieur, traiteurs, bijoutiers, hôtels, services haut de gamme',
            'primary' => '#a16207',
            'secondary' => '#1c1917',
            'image_style' => 'refined luxury atmosphere, elegant details, soft dramatic lighting, rich deep tones, premium materials',
            'structure' => ['nav_layout' => 'centered', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_layout' => 'boxed', 'hero_align' => 'center', 'services_style' => 'rows', 'about_layout' => 'split_reverse', 'highlights_style' => 'band', 'gallery_style' => 'mosaic', 'cta_style' => 'band', 'footer_layout' => 'centered', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'classique', 'radius' => 'none', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'dark', 'hero_background' => 'dark', 'surface' => 'white', 'cards' => 'flat', 'section_alt' => 'neutral', 'titles' => 'centered', 'footer' => 'dark', 'headings' => 'uppercase', 'density' => 'airy'],
        ],
        'terroir' => [
            'label' => 'Terroir',
            'inspiration' => 'Twenty Twenty-Four',
            'description' => 'Authentique : page encadrée sur fond crème, titre centré au-dessus d\'une grande photo, galerie libre.',
            'ideal_for' => 'boulangeries, restaurants, producteurs, cavistes, paysagistes, fleuristes',
            'primary' => '#9a3412',
            'secondary' => '#422006',
            'image_style' => 'authentic and rustic mood, golden hour light, natural textures such as wood, linen, stone and fresh produce, warm film-like grading',
            'structure' => ['nav_layout' => 'centered', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_layout' => 'stacked', 'hero_align' => 'center', 'services_style' => 'cards', 'about_layout' => 'overlap', 'highlights_style' => 'boxes', 'gallery_style' => 'masonry', 'cta_style' => 'card', 'footer_layout' => 'columns', 'page_layout' => 'boxed'],
            'style' => ['font_pair' => 'editorial', 'radius' => 'small', 'buttons' => 'rounded', 'shadow' => 'none', 'header' => 'light', 'hero_background' => 'tint', 'surface' => 'cream', 'cards' => 'bordered', 'section_alt' => 'warm', 'titles' => 'left', 'footer' => 'brand', 'headings' => 'normal', 'density' => 'airy'],
        ],
        'nocturne' => [
            'label' => 'Nocturne',
            'inspiration' => 'OceanWP / Blocksy (sombre)',
            'description' => 'Sombre et percutant : menu burger sur photo plein écran, tuiles photo, bandeau de points forts.',
            'ideal_for' => 'garages, barbiers, tatoueurs, salles de sport, bars, DJ et événementiel',
            'primary' => '#dc2626',
            'secondary' => '#0a0a0a',
            'image_style' => 'moody low-key lighting, dark background, strong contrast, cinematic urban mood, subtle colored accent light',
            'structure' => ['nav_layout' => 'burger', 'topbar' => 'none', 'header_overlay' => 'yes', 'hero_layout' => 'image', 'hero_align' => 'left', 'services_style' => 'tiles', 'about_layout' => 'split_reverse', 'highlights_style' => 'band', 'gallery_style' => 'mosaic', 'cta_style' => 'minimal', 'footer_layout' => 'minimal', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'technique', 'radius' => 'small', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'dark', 'hero_background' => 'dark', 'surface' => 'dark', 'cards' => 'bordered', 'section_alt' => 'neutral', 'titles' => 'accent', 'footer' => 'dark', 'headings' => 'uppercase', 'density' => 'comfortable'],
        ],
        'cocon' => [
            'label' => 'Cocon',
            'inspiration' => 'Neve (bien-être)',
            'description' => 'Doux et apaisant : logo centré, photo à gauche du titre, formes arrondies, encadrés de points forts.',
            'ideal_for' => 'esthéticiennes, spas, sophrologues, praticiens bien-être, coiffeurs, fleuristes',
            'primary' => '#9d4e6b',
            'secondary' => '#3f1d2b',
            'image_style' => 'soft and serene mood, nude and pastel tones, gentle diffused light, delicate textures, calm wellbeing atmosphere',
            'structure' => ['nav_layout' => 'centered', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_layout' => 'split_reverse', 'hero_align' => 'left', 'services_style' => 'cards', 'about_layout' => 'banner', 'highlights_style' => 'boxes', 'gallery_style' => 'masonry', 'cta_style' => 'card', 'footer_layout' => 'centered', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'chaleureux', 'radius' => 'large', 'buttons' => 'pill', 'shadow' => 'soft', 'header' => 'light', 'hero_background' => 'gradient', 'surface' => 'white', 'cards' => 'elevated', 'section_alt' => 'tint', 'titles' => 'centered', 'footer' => 'light', 'headings' => 'normal', 'density' => 'airy'],
        ],
        'chantier' => [
            'label' => 'Chantier',
            'inspiration' => 'OceanWP / Sydney (construction)',
            'description' => 'Robuste et direct : barre d\'infos, photo plein écran au texte encadré, services numérotés.',
            'ideal_for' => 'maçons, couvreurs, terrassiers, charpentiers, entreprises de rénovation',
            'primary' => '#b45309',
            'secondary' => '#111827',
            'image_style' => 'robust and dynamic mood, bright outdoor daylight, heavy-duty materials and equipment, sharp detail, strong lines',
            'structure' => ['nav_layout' => 'top', 'topbar' => 'infos', 'header_overlay' => 'no', 'hero_layout' => 'boxed', 'hero_align' => 'left', 'services_style' => 'numbered', 'about_layout' => 'split', 'highlights_style' => 'band', 'gallery_style' => 'grid', 'cta_style' => 'band', 'footer_layout' => 'columns', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'moderne', 'radius' => 'none', 'buttons' => 'square', 'shadow' => 'strong', 'header' => 'light', 'hero_background' => 'dark', 'surface' => 'white', 'cards' => 'accent', 'section_alt' => 'neutral', 'titles' => 'accent', 'footer' => 'dark', 'headings' => 'uppercase', 'density' => 'comfortable'],
        ],
        'cabinet' => [
            'label' => 'Cabinet',
            'inspiration' => 'Hestia (corporate)',
            'description' => 'Sérieux et institutionnel : page encadrée, barre d\'infos, services en lignes, titres à empattements.',
            'ideal_for' => 'avocats, notaires, comptables, cabinets médicaux et paramédicaux, assureurs',
            'primary' => '#1e40af',
            'secondary' => '#0b1a33',
            'image_style' => 'professional and reassuring mood, tidy modern office or practice setting, neutral palette, soft even light',
            'structure' => ['nav_layout' => 'top', 'topbar' => 'infos', 'header_overlay' => 'no', 'hero_layout' => 'split', 'hero_align' => 'left', 'services_style' => 'rows', 'about_layout' => 'split_reverse', 'highlights_style' => 'boxes', 'gallery_style' => 'grid', 'cta_style' => 'card', 'footer_layout' => 'columns', 'page_layout' => 'boxed'],
            'style' => ['font_pair' => 'classique', 'radius' => 'small', 'buttons' => 'rounded', 'shadow' => 'soft', 'header' => 'light', 'hero_background' => 'tint', 'surface' => 'white', 'cards' => 'bordered', 'section_alt' => 'neutral', 'titles' => 'centered', 'footer' => 'brand', 'headings' => 'normal', 'density' => 'comfortable'],
        ],
        'studio' => [
            'label' => 'Studio',
            'inspiration' => 'Blocksy / Hello (créatif)',
            'description' => 'Créatif : menu latéral fixe à gauche, photo plein écran, galerie avec photo vedette.',
            'ideal_for' => 'photographes, architectes, designers, artisans d\'art, galeries',
            'primary' => '#18181b',
            'secondary' => '#27272a',
            'image_style' => 'artistic editorial photography, bold composition, strong graphic lines, muted palette, gallery-like mood',
            'structure' => ['nav_layout' => 'sidebar_left', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_layout' => 'image', 'hero_align' => 'left', 'services_style' => 'rows', 'about_layout' => 'banner', 'highlights_style' => 'columns', 'gallery_style' => 'mosaic', 'cta_style' => 'minimal', 'footer_layout' => 'minimal', 'page_layout' => 'full'],
            'style' => ['font_pair' => 'editorial', 'radius' => 'none', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'light', 'hero_background' => 'dark', 'surface' => 'white', 'cards' => 'flat', 'section_alt' => 'none', 'titles' => 'left', 'footer' => 'light', 'headings' => 'normal', 'density' => 'airy'],
        ],
    ];

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::ALL);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $template): string => $template['label'], self::ALL);
    }

    /**
     * Design complet du modèle, avec ses couleurs ou celles fournies.
     *
     * @return array<string, mixed>
     */
    public static function design(string $key, ?string $primary = null, ?string $secondary = null): array
    {
        $template = self::ALL[$key];

        return [
            ...$template['structure'],
            ...$template['style'],
            'template' => $key,
            'primary' => $primary ?? $template['primary'],
            'secondary' => $primary === null ? $template['secondary'] : $secondary,
        ];
    }

    /**
     * Mise en page du bandeau d'accueil prévue par le modèle quand une image est disponible.
     */
    public static function heroLayout(?string $key): string
    {
        $layout = self::exists($key) ? self::ALL[$key]['structure']['hero_layout'] : 'split';

        return $layout === 'plain' ? 'split' : $layout;
    }

    /**
     * Direction photographique (en anglais) à transmettre au générateur d'images.
     */
    public static function imageStyle(?string $key): ?string
    {
        return self::exists($key) ? self::ALL[$key]['image_style'] : null;
    }
}
