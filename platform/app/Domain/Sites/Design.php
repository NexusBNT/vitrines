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
     * Choix possibles pour chaque jeton, avec leur libellé pour l'administration.
     *
     * @var array<string, array<string, string>>
     */
    public const OPTIONS = [
        'radius' => ['none' => 'Angles droits', 'small' => 'Arrondi léger', 'medium' => 'Arrondi moyen', 'large' => 'Arrondi prononcé'],
        'buttons' => ['pill' => 'Pilule', 'rounded' => 'Arrondis', 'square' => 'Carrés'],
        'shadow' => ['none' => 'Aucune ombre', 'soft' => 'Ombres douces', 'strong' => 'Ombres marquées'],
        'header' => ['light' => 'Clair', 'primary' => 'Couleur principale', 'dark' => 'Sombre'],
        'hero_layout' => ['split' => 'Texte et photo côte à côte', 'image' => 'Photo plein écran', 'plain' => 'Texte seul'],
        'hero_background' => ['tint' => 'Teinte légère', 'gradient' => 'Dégradé doux', 'primary' => 'Couleur principale', 'dark' => 'Sombre'],
        'cards' => ['elevated' => 'Ombrées', 'bordered' => 'Bordées', 'accent' => 'Liseré de couleur', 'flat' => 'À plat'],
        'section_alt' => ['tint' => 'Teinte de la couleur principale', 'neutral' => 'Gris neutre', 'warm' => 'Beige chaud', 'none' => 'Fond uni'],
        'footer' => ['dark' => 'Sombre', 'brand' => 'Couleur secondaire', 'light' => 'Clair'],
        'headings' => ['normal' => 'Casse normale', 'uppercase' => 'Majuscules'],
        'density' => ['comfortable' => 'Standard', 'airy' => 'Aéré'],
    ];

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
            $normalized[$token] = array_key_exists($design[$token] ?? '', $choices) ? $design[$token] : $fallback[$token];
        }

        foreach (['name', 'rationale'] as $text) {
            if (filled($design[$text] ?? null)) {
                $normalized[$text] = mb_substr(trim((string) $design[$text]), 0, $text === 'name' ? 60 : 400);
            }
        }

        return $normalized;
    }

    /**
     * Classes CSS posées sur <body> pour les variantes structurelles.
     *
     * @param  array<string, mixed>  $design
     */
    public static function bodyClasses(array $design): string
    {
        return collect(['buttons' => 'btn', 'header' => 'hdr', 'hero_background' => 'hero-bg', 'cards' => 'cards', 'section_alt' => 'alt', 'footer' => 'ftr', 'headings' => 'headings', 'shadow' => 'shadow'])
            ->map(fn (string $prefix, string $token): string => $prefix.'-'.$design[$token])
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
