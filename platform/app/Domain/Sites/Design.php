<?php

namespace App\Domain\Sites;

use Illuminate\Support\Facades\File;

/**
 * Jetons de design d'un site : un ensemble fermé de choix que les templates savent rendre.
 *
 * L'IA, l'équipe ou les préréglages d'ambiance ne font que choisir parmi ces valeurs :
 * le rendu reste maîtrisé, accessible et performant quel que soit le choix.
 */
final class Design
{
    /**
     * @var array<string, array{label: string, heading: ?string, body: ?string}>
     */
    public const FONT_PAIRS = [
        'system' => ['label' => 'Système (le plus rapide)', 'heading' => null, 'body' => null],
        'moderne' => ['label' => 'Moderne — Manrope / Inter', 'heading' => 'manrope', 'body' => 'inter'],
        'editorial' => ['label' => 'Éditorial — Fraunces / Inter', 'heading' => 'fraunces', 'body' => 'inter'],
        'classique' => ['label' => 'Classique — Playfair Display / Source Sans 3', 'heading' => 'playfair-display', 'body' => 'source-sans-3'],
        'chaleureux' => ['label' => 'Chaleureux — Lora / Nunito', 'heading' => 'lora', 'body' => 'nunito'],
        'technique' => ['label' => 'Technique — Space Grotesk / Inter', 'heading' => 'space-grotesk', 'body' => 'inter'],
        'artisanal' => ['label' => 'Artisanal — DM Serif Display / DM Sans', 'heading' => 'dm-serif-display', 'body' => 'dm-sans'],
    ];

    /**
     * Jetons de structure : ils changent la disposition des éléments (position du menu,
     * type de bandeau, agencement des sections, pied de page).
     *
     * @var array<string, array<string, string>>
     */
    public const STRUCTURE = [
        'nav_layout' => ['top' => 'Menu en haut', 'centered' => 'Logo centré, menu dessous', 'sidebar_left' => 'Menu latéral à gauche', 'sidebar_right' => 'Menu latéral à droite', 'burger' => 'Menu replié (burger)'],
        'topbar' => ['none' => 'Sans barre d\'infos', 'infos' => 'Barre d\'infos au-dessus du menu'],
        'header_overlay' => ['no' => 'En-tête au-dessus du bandeau', 'yes' => 'En-tête transparent sur la photo'],
        'hero_layout' => ['split' => 'Texte à gauche, photo à droite', 'split_reverse' => 'Photo à gauche, texte à droite', 'image' => 'Photo plein écran', 'boxed' => 'Photo plein écran, texte encadré', 'stacked' => 'Titre centré, grande photo dessous', 'plain' => 'Texte seul'],
        'hero_align' => ['left' => 'Texte aligné à gauche', 'center' => 'Texte centré'],
        'services_style' => ['cards' => 'Cartes', 'tiles' => 'Tuiles photo', 'numbered' => 'Liste numérotée', 'rows' => 'Lignes'],
        'about_layout' => ['split' => 'Texte puis photo', 'split_reverse' => 'Photo puis texte', 'banner' => 'Photo en bannière', 'overlap' => 'Texte posé sur la photo'],
        'highlights_style' => ['columns' => 'Colonnes', 'boxes' => 'Encadrés', 'band' => 'Bandeau en couleur'],
        'gallery_style' => ['grid' => 'Grille régulière', 'masonry' => 'Mosaïque libre', 'mosaic' => 'Photo vedette'],
        'cta_style' => ['band' => 'Bandeau pleine largeur', 'card' => 'Encart arrondi', 'minimal' => 'Sobre, centré'],
        'footer_layout' => ['columns' => 'Colonnes', 'centered' => 'Centré', 'minimal' => 'Une ligne'],
        'page_layout' => ['full' => 'Pleine largeur', 'boxed' => 'Page encadrée'],
    ];

    /**
     * Jetons de style : couleurs d'ambiance, formes, typographie, espacements.
     *
     * @var array<string, array<string, string>>
     */
    public const STYLE = [
        'surface' => ['white' => 'Fond blanc', 'cream' => 'Fond crème', 'dark' => 'Fond sombre'],
        'header' => ['light' => 'Clair', 'primary' => 'Couleur principale', 'dark' => 'Sombre'],
        'hero_background' => ['tint' => 'Teinte légère', 'gradient' => 'Dégradé doux', 'primary' => 'Couleur principale', 'dark' => 'Sombre'],
        'section_alt' => ['tint' => 'Teinte de la couleur principale', 'neutral' => 'Gris neutre', 'warm' => 'Beige chaud', 'none' => 'Fond uni'],
        'footer' => ['dark' => 'Sombre', 'brand' => 'Couleur secondaire', 'light' => 'Clair'],
        'radius' => ['none' => 'Angles droits', 'small' => 'Arrondi léger', 'medium' => 'Arrondi moyen', 'large' => 'Arrondi prononcé'],
        'buttons' => ['pill' => 'Pilule', 'rounded' => 'Arrondis', 'square' => 'Carrés'],
        'shadow' => ['none' => 'Aucune ombre', 'soft' => 'Ombres douces', 'strong' => 'Ombres marquées'],
        'cards' => ['elevated' => 'Ombrées', 'bordered' => 'Bordées', 'accent' => 'Liseré de couleur', 'flat' => 'À plat'],
        'titles' => ['left' => 'À gauche', 'centered' => 'Centrés', 'accent' => 'Soulignés d\'un trait de couleur'],
        'headings' => ['normal' => 'Casse normale', 'uppercase' => 'Majuscules'],
        'density' => ['comfortable' => 'Standard', 'airy' => 'Aéré'],
    ];

    /**
     * Tous les jetons à choix fermé.
     *
     * @var array<string, array<string, string>>
     */
    public const OPTIONS = self::STRUCTURE + self::STYLE;

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'nav_layout' => 'Navigation',
        'topbar' => 'Barre d\'infos',
        'header_overlay' => 'En-tête sur la photo',
        'hero_layout' => 'Bandeau d\'accueil',
        'hero_align' => 'Alignement du bandeau',
        'services_style' => 'Services',
        'about_layout' => 'Présentation',
        'highlights_style' => 'Points forts',
        'gallery_style' => 'Galerie',
        'cta_style' => 'Appel à l\'action',
        'footer_layout' => 'Pied de page',
        'page_layout' => 'Largeur de page',
        'surface' => 'Fond du site',
        'header' => 'Couleur de l\'en-tête',
        'hero_background' => 'Fond du bandeau sans photo',
        'section_alt' => 'Fond des sections alternées',
        'footer' => 'Couleur du pied de page',
        'radius' => 'Arrondis',
        'buttons' => 'Boutons',
        'shadow' => 'Ombres',
        'cards' => 'Cartes',
        'titles' => 'Titres de section',
        'headings' => 'Casse des titres',
        'density' => 'Espacement',
    ];

    /**
     * Structure historique : les designs enregistrés avant l'arrivée de ces jetons gardent leur rendu.
     */
    private const STRUCTURE_DEFAULTS = ['nav_layout' => 'top', 'topbar' => 'none', 'header_overlay' => 'no', 'hero_align' => 'left', 'services_style' => 'cards', 'about_layout' => 'split', 'highlights_style' => 'columns', 'gallery_style' => 'grid', 'cta_style' => 'band', 'footer_layout' => 'columns', 'page_layout' => 'full', 'surface' => 'white', 'titles' => 'left'];

    private const PRESETS = [
        'sobre' => ['font_pair' => 'system', 'radius' => 'small', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'light', 'hero_layout' => 'split', 'hero_background' => 'tint', 'cards' => 'bordered', 'section_alt' => 'neutral', 'footer' => 'dark', 'headings' => 'normal', 'density' => 'comfortable'],
        'chaleureux' => ['font_pair' => 'chaleureux', 'radius' => 'large', 'buttons' => 'pill', 'shadow' => 'soft', 'header' => 'light', 'hero_layout' => 'split', 'hero_background' => 'gradient', 'cards' => 'elevated', 'section_alt' => 'warm', 'footer' => 'dark', 'headings' => 'normal', 'density' => 'airy'],
        'moderne' => ['font_pair' => 'moderne', 'radius' => 'medium', 'buttons' => 'pill', 'shadow' => 'soft', 'header' => 'light', 'hero_layout' => 'split', 'hero_background' => 'gradient', 'cards' => 'accent', 'section_alt' => 'tint', 'footer' => 'dark', 'headings' => 'normal', 'density' => 'comfortable'],
        'premium' => ['font_pair' => 'classique', 'radius' => 'none', 'buttons' => 'square', 'shadow' => 'none', 'header' => 'dark', 'hero_layout' => 'image', 'hero_background' => 'dark', 'cards' => 'flat', 'section_alt' => 'neutral', 'footer' => 'dark', 'headings' => 'uppercase', 'density' => 'airy'],
    ];

    /**
     * Design par défaut correspondant à l'ambiance choisie dans le brief.
     *
     * @return array<string, string>
     */
    public static function fromStyle(?string $style, string $primary, ?string $secondary = null): array
    {
        return [
            ...self::STRUCTURE_DEFAULTS,
            ...(self::PRESETS[$style] ?? self::PRESETS['moderne']),
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * Design effectif d'une spécification de site (jetons enregistrés, sinon préréglage de l'ambiance).
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public static function forSpec(array $spec): array
    {
        $theme = $spec['theme'];
        $fallback = self::fromStyle($theme['style'] ?? null, $theme['colors']['primary'] ?? '#1d4ed8', $theme['colors']['secondary'] ?? null);

        return self::normalize($theme['design'] ?? [], $fallback);
    }

    /**
     * Garde uniquement des valeurs connues ; toute valeur absente ou invalide reprend celle de $fallback.
     *
     * @param  array<string, mixed>  $design
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public static function normalize(array $design, array $fallback): array
    {
        $normalized = [];

        foreach (['primary', 'secondary'] as $color) {
            $value = $design[$color] ?? null;
            $normalized[$color] = is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : ($fallback[$color] ?? null);
        }

        $normalized['font_pair'] = array_key_exists($design['font_pair'] ?? '', self::FONT_PAIRS) ? $design['font_pair'] : $fallback['font_pair'];

        foreach (self::OPTIONS as $token => $choices) {
            $normalized[$token] = array_key_exists($design[$token] ?? '', $choices) ? $design[$token] : ($fallback[$token] ?? self::STRUCTURE_DEFAULTS[$token] ?? array_key_first($choices));
        }

        $template = collect([$design['template'] ?? null, $fallback['template'] ?? null])->first(fn (mixed $key): bool => is_string($key) && SiteTemplates::exists($key));

        if ($template !== null) {
            $normalized['template'] = $template;
        }

        foreach (['name', 'rationale'] as $text) {
            if (filled($design[$text] ?? null)) {
                $normalized[$text] = mb_substr(trim((string) $design[$text]), 0, $text === 'name' ? 60 : 400);
            }
        }

        return $normalized;
    }

    /**
     * Classes CSS posées sur <body> : une par jeton à choix fermé (ex. « nav-sidebar-left »).
     *
     * @param  array<string, mixed>  $design
     */
    public static function bodyClasses(array $design): string
    {
        $prefixes = [
            'nav_layout' => 'nav', 'topbar' => 'topbar', 'header_overlay' => 'overlay', 'hero_align' => 'hero-align',
            'services_style' => 'svc', 'about_layout' => 'about', 'highlights_style' => 'hl', 'gallery_style' => 'gal',
            'cta_style' => 'action', 'footer_layout' => 'fl', 'page_layout' => 'pl', 'surface' => 'surface',
            'header' => 'hdr', 'hero_background' => 'hero-bg', 'section_alt' => 'alt', 'footer' => 'ftr',
            'buttons' => 'btn', 'shadow' => 'shadow', 'cards' => 'cards', 'titles' => 'titles', 'headings' => 'headings',
        ];

        return collect($prefixes)
            ->map(fn (string $prefix, string $token): string => $prefix.'-'.str_replace('_', '-', $design[$token]))
            ->implode(' ');
    }

    /**
     * Variables CSS dérivées des jetons numériques (arrondis, espacements, polices).
     *
     * @param  array<string, mixed>  $design
     */
    public static function cssVariables(array $design): string
    {
        [$radius, $radiusSmall] = ['none' => ['0', '0'], 'small' => ['6px', '4px'], 'medium' => ['14px', '8px'], 'large' => ['22px', '12px']][$design['radius']];
        $sectionPad = $design['density'] === 'airy' ? 'clamp(64px, 10vw, 128px)' : 'clamp(56px, 8vw, 96px)';

        $variables = [
            '--radius' => $radius,
            '--radius-small' => $radiusSmall,
            '--section-pad' => $sectionPad,
            '--font-heading' => self::fontStack(self::FONT_PAIRS[$design['font_pair']]['heading']),
            '--font-body' => self::fontStack(self::FONT_PAIRS[$design['font_pair']]['body']),
        ];

        return collect($variables)->map(fn (string $value, string $name): string => $name.':'.$value)->implode(';');
    }

    /**
     * Polices à embarquer dans le build (identifiants du dossier templates/fonts).
     *
     * @param  array<string, mixed>  $design
     * @return list<string>
     */
    public static function fonts(array $design): array
    {
        $pair = self::FONT_PAIRS[$design['font_pair']];

        return array_values(array_unique(array_filter([$pair['heading'], $pair['body']])));
    }

    /**
     * @return array<string, array{family: string, weight: string, fallback: string}>
     */
    public static function fontCatalog(): array
    {
        return once(fn (): array => json_decode(File::get(config('vitrines.templates_path').'/fonts/fonts.json'), true));
    }

    private static function fontStack(?string $font): string
    {
        $system = 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';

        if ($font === null) {
            return $system;
        }

        $entry = self::fontCatalog()[$font];

        return '"'.$entry['family'].'",'.$entry['fallback'];
    }
}
