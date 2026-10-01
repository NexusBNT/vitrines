<?php

namespace App\Domain\Sites;

/**
 * Miniature schématique (SVG) d'un design : elle montre la structure de la page d'accueil
 * (navigation, bandeau, sections, pied de page) avec les couleurs et les formes du design.
 *
 * Sert à présenter les thèmes, les propositions de l'IA et les choix de structure dans l'administration.
 */
final class DesignWireframe
{
    private const WIDTH = 240;

    private const SIDEBAR = 54;

    private const PHOTO = '#9fb1c3';

    private const PHOTO_DARK = '#6b7f94';

    /**
     * Région de la page à cadrer pour chaque jeton (les autres montrent la page entière).
     */
    private const FOCUS = [
        'nav_layout' => 'top', 'topbar' => 'top', 'header_overlay' => 'top', 'hero_layout' => 'top', 'hero_align' => 'top',
        'services_style' => 'services', 'about_layout' => 'about', 'highlights_style' => 'highlights',
        'gallery_style' => 'gallery', 'cta_style' => 'cta', 'footer_layout' => 'footer',
    ];

    /** Compteur partagé : plusieurs miniatures cohabitent dans la même page HTML. */
    private static int $clipCount = 0;

    /** @var list<string> */
    private array $shapes = [];

    /** @var array<string, array{float, float}> */
    private array $regions = [];

    /** @var array<string, string> */
    private array $palette;

    private string $bg;

    private string $ink;

    private string $soft;

    private float $x0;

    private float $x1;

    /**
     * @param  array<string, mixed>  $design
     */
    private function __construct(private array $design) {}

    /**
     * @param  array<string, mixed>  $design  Design normalisé (voir Design::normalize)
     * @param  string|null  $focus  Jeton dont on cadre la région (ex. « services_style »)
     */
    public static function svg(array $design, ?string $focus = null): string
    {
        return (new self($design))->render($focus === null ? null : (self::FOCUS[$focus] ?? null));
    }

    private function render(?string $region): string
    {
        $design = $this->design;
        $this->palette = ColorPalette::from($design['primary'] ?? '#1d4ed8', $design['secondary'] ?? null);
        $dark = $design['surface'] === 'dark';
        $this->bg = ['white' => '#ffffff', 'cream' => '#fbf7f0', 'dark' => '#111318'][$design['surface']];
        $this->ink = $dark ? '#e8eaed' : '#1f2933';
        $this->soft = $dark ? $this->palette['primary_deep'] : $this->palette['primary_soft'];

        $boxed = $design['page_layout'] === 'boxed';
        $sidebar = in_array($design['nav_layout'], ['sidebar_left', 'sidebar_right'], true);
        $shell0 = $boxed ? 12.0 : 0.0;
        $shell1 = self::WIDTH - $shell0;
        $this->x0 = $shell0 + ($design['nav_layout'] === 'sidebar_left' ? self::SIDEBAR : 0);
        $this->x1 = $shell1 - ($design['nav_layout'] === 'sidebar_right' ? self::SIDEBAR : 0);

        $body = [];
        $y = $boxed ? 6.0 : 0.0;
        $start = $y;

        if ($design['topbar'] === 'infos' && ! $sidebar) {
            $this->rect($shell0, $y, $shell1 - $shell0, 6, $this->palette['secondary']);
            $this->line($shell0 + 8, $y + 3, 26, $this->palette['secondary_ink'], 0.7, 1.2);
            $this->line($shell1 - 38, $y + 3, 30, $this->palette['secondary_ink'], 0.7, 1.2);
            $y += 6;
        }

        $heroWithPhoto = in_array($design['hero_layout'], ['image', 'boxed'], true);
        $overlay = ! $sidebar && $design['header_overlay'] === 'yes' && $heroWithPhoto;
        $headerTop = $y;

        if (! $sidebar && ! $overlay) {
            $y = $this->header($shell0, $shell1, $y, false);
        }

        $y = $this->hero($y);

        if ($overlay) {
            $this->header($shell0, $shell1, $headerTop, true);
        }

        $this->regions['top'] = [$start, $y];
        $y = $this->section('services', $y, 52, fn (float $top) => $this->services($top));
        $y = $this->section('about', $y, 46, fn (float $top) => $this->about($top), alt: true);
        $y = $this->section('highlights', $y, 30, fn (float $top) => $this->highlights($top));
        $y = $this->section('gallery', $y, 44, fn (float $top) => $this->gallery($top), alt: true);
        $y = $this->section('cta', $y, 28, fn (float $top) => $this->cta($top));
        $y = $this->section('footer', $y, $design['footer_layout'] === 'minimal' ? 12 : 26, fn (float $top) => $this->footer($top));

        if ($sidebar) {
            $this->sidebar($design['nav_layout'] === 'sidebar_left' ? $shell0 : $shell1 - self::SIDEBAR, $start, $y);
        }

        $height = $y + ($boxed ? 6 : 0);

        if ($boxed) {
            $body[] = sprintf('<rect width="%d" height="%s" fill="%s"/>', self::WIDTH, $this->n($height), $design['surface'] === 'dark' ? '#000' : '#e2dfd9');
            $body[] = sprintf('<rect x="%s" y="%s" width="%s" height="%s" fill="%s" rx="3"/>', $this->n($shell0), $this->n($start), $this->n($shell1 - $shell0), $this->n($y - $start), $this->bg);
        } else {
            $body[] = sprintf('<rect width="%d" height="%s" fill="%s"/>', self::WIDTH, $this->n($height), $this->bg);
        }

        [$top, $bottom] = $region !== null ? $this->regions[$region] : [0, $height];

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 %s %d %s" role="img" aria-hidden="true" style="display:block;width:100%%;height:auto">%s</svg>',
            $this->n($top),
            self::WIDTH,
            $this->n($bottom - $top),
            implode('', [...$body, ...$this->shapes]),
        );
    }

    /**
     * @param  callable(float): void  $draw
     */
    private function section(string $name, float $y, float $height, callable $draw, bool $alt = false): float
    {
        if ($alt && $this->design['section_alt'] !== 'none') {
            $color = match ($this->design['section_alt']) {
                'tint' => $this->soft,
                'neutral' => $this->design['surface'] === 'dark' ? '#1a1d24' : '#f1f2f4',
                default => $this->design['surface'] === 'dark' ? '#1a1d24' : '#f5efe5',
            };
            $this->rect($this->x0, $y, $this->x1 - $this->x0, $height, $color);
        }

        $draw($y);
        $this->regions[$name] = [$y, $y + $height];

        return $y + $height;
    }

    private function header(float $x0, float $x1, float $y, bool $overlay): float
    {
        $centered = $this->design['nav_layout'] === 'centered';
        $height = $centered ? 24 : 16;
        [$background, $ink] = match (true) {
            $overlay => [null, '#ffffff'],
            $this->design['header'] === 'primary' => [$this->palette['primary'], $this->palette['primary_ink']],
            $this->design['header'] === 'dark' => ['#111827', '#ffffff'],
            default => [$this->bg, $this->ink],
        };

        if ($background !== null) {
            $this->rect($x0, $y, $x1 - $x0, $height, $background);
            $this->rect($x0, $y + $height - 0.4, $x1 - $x0, 0.4, $ink, 0.12);
        }

        $mark = $this->design['header'] === 'primary' && ! $overlay ? $this->palette['primary_ink'] : $this->palette['primary'];

        if ($centered) {
            $middle = ($x0 + $x1) / 2;
            $this->rect($middle - 14, $y + 4, 6, 6, $mark, 1, 1);
            $this->line($middle - 6, $y + 7, 20, $ink, 0.8, 1.6);

            foreach ([-30, -10, 10] as $offset) {
                $this->line($middle + $offset, $y + 17, 16, $ink, 0.55, 1.2);
            }

            return $y + $height;
        }

        $this->rect($x0 + 8, $y + 5, 6, 6, $mark, 1, 1);
        $this->line($x0 + 17, $y + 8, 22, $ink, 0.8, 1.6);

        if ($this->design['nav_layout'] === 'burger') {
            foreach ([5.5, 8, 10.5] as $offset) {
                $this->line($x1 - 18, $y + $offset, 9, $ink, 0.9, 1.2);
            }
        } else {
            foreach ([0, 1, 2] as $index) {
                $this->line($x1 - 72 + $index * 18, $y + 8, 13, $ink, 0.55, 1.2);
            }

            $this->rect($x1 - 22, $y + 4.5, 15, 7, $this->palette['primary'], 1, $this->buttonRadius());
        }

        return $y + $height;
    }

    private function sidebar(float $x, float $top, float $bottom): void
    {
        [$background, $ink] = match ($this->design['header']) {
            'primary' => [$this->palette['primary'], $this->palette['primary_ink']],
            'dark' => ['#111827', '#ffffff'],
            default => [$this->bg, $this->ink],
        };

        $this->rect($x, $top, self::SIDEBAR, $bottom - $top, $background);
        $this->rect($this->design['nav_layout'] === 'sidebar_left' ? $x + self::SIDEBAR - 0.4 : $x, $top, 0.4, $bottom - $top, $ink, 0.15);
        $this->rect($x + 8, $top + 10, 8, 8, $this->design['header'] === 'primary' ? $this->palette['primary_ink'] : $this->palette['primary'], 1, 1);
        $this->line($x + 8, $top + 25, 30, $ink, 0.8, 1.6);

        foreach (range(0, 4) as $index) {
            $this->line($x + 8, $top + 38 + $index * 9, $index === 0 ? 24 : 30, $index === 0 ? $this->palette['primary'] : $ink, $index === 0 ? 1 : 0.5, 1.3);
        }

        $this->rect($x + 8, $top + 90, self::SIDEBAR - 16, 8, $this->palette['primary'], 1, $this->buttonRadius());
    }

    private function hero(float $y): float
    {
        $layout = $this->design['hero_layout'];
        $height = $layout === 'stacked' ? 88 : 74;
        $x0 = $this->x0;
        $width = $this->x1 - $this->x0;
        $center = $this->design['hero_align'] === 'center';

        if (in_array($layout, ['image', 'boxed'], true)) {
            $this->photo($x0, $y, $width, $height, 0);
            $this->rect($x0, $y, $width, $height, '#000000', $layout === 'boxed' ? 0.15 : ($center ? 0.5 : 0.35));

            if ($layout === 'boxed') {
                $boxWidth = 92;
                $boxX = $center ? $x0 + ($width - $boxWidth) / 2 : $x0 + 14;
                $this->rect($boxX, $y + 18, $boxWidth, 44, $this->bg, 1, $this->radius());
                $this->text($boxX + 8, $y + 26, $boxWidth - 16, $center, $this->ink);
            } else {
                $this->text($center ? $x0 + $width / 2 - 50 : $x0 + 14, $y + ($center ? 26 : 34), 100, $center, '#ffffff');
            }

            return $y + $height;
        }

        $this->rect($x0, $y, $width, $height, $this->heroBackground());
        $ink = in_array($this->design['hero_background'], ['primary', 'dark'], true) ? ($this->design['hero_background'] === 'primary' ? $this->palette['primary_ink'] : '#ffffff') : $this->ink;

        match ($layout) {
            'split' => [$this->text($x0 + 12, $y + 20, ($width - 36) / 2, false, $ink), $this->photo($x0 + $width / 2 + 4, $y + 12, $width / 2 - 16, $height - 24)],
            'split_reverse' => [$this->photo($x0 + 12, $y + 12, $width / 2 - 16, $height - 24), $this->text($x0 + $width / 2 + 8, $y + 20, ($width - 36) / 2, false, $ink)],
            'stacked' => [$this->text($x0 + $width / 2 - 50, $y + 8, 100, true, $ink), $this->photo($x0 + 12, $y + 46, $width - 24, $height - 46, bottomOnly: true)],
            default => $this->text($center ? $x0 + $width / 2 - 55 : $x0 + 12, $y + 22, 110, $center, $ink),
        };

        return $y + $height;
    }

    private function text(float $x, float $y, float $width, bool $center, string $ink): void
    {
        $line = function (float $lineY, float $length, float $opacity, float $thickness) use ($x, $width, $center, $ink): void {
            $this->line($center ? $x + ($width - $length) / 2 : $x, $lineY, $length, $ink, $opacity, $thickness);
        };

        $line($y, $width * 0.9, 0.9, 3.2);
        $line($y + 6, $width * 0.65, 0.9, 3.2);
        $line($y + 13, $width * 0.95, 0.45, 1.3);
        $line($y + 17, $width * 0.8, 0.45, 1.3);

        $buttonX = $center ? $x + $width / 2 - 14 : $x;
        $this->rect($buttonX, $y + 22, 28, 7, $this->palette['primary'], 1, $this->buttonRadius());
    }

    private function services(float $y): void
    {
        $this->title($y + 8);
        $style = $this->design['services_style'];
        $x0 = $this->x0 + 12;
        $width = $this->x1 - $this->x0 - 24;
        $top = $y + 18;

        if ($style === 'numbered' || $style === 'rows') {
            foreach ([0, 1] as $row) {
                foreach ($style === 'numbered' ? [0, 1] : [0] as $column) {
                    $cellWidth = $style === 'numbered' ? $width / 2 - 4 : $width;
                    $cellX = $x0 + $column * ($cellWidth + 8);
                    $cellY = $top + $row * 15;
                    $this->rect($cellX, $cellY, $cellWidth, 0.4, $this->ink, 0.2);

                    if ($style === 'numbered') {
                        $this->line($cellX, $cellY + 7, 7, $this->palette['primary'], 1, 4);
                        $this->line($cellX + 11, $cellY + 5, $cellWidth * 0.5, $this->ink, 0.8, 1.6);
                        $this->line($cellX + 11, $cellY + 9, $cellWidth * 0.7, $this->ink, 0.4, 1.1);
                    } else {
                        $this->photo($cellX, $cellY + 2, 26, 11, $this->smallRadius());
                        $this->line($cellX + 32, $cellY + 6, 50, $this->ink, 0.8, 1.6);
                        $this->line($cellX + 32, $cellY + 10, 110, $this->ink, 0.4, 1.1);
                    }
                }
            }

            return;
        }

        $cardWidth = ($width - 12) / 3;

        foreach ([0, 1, 2] as $index) {
            $cardX = $x0 + $index * ($cardWidth + 6);

            if ($style === 'tiles') {
                $this->photo($cardX, $top, $cardWidth, 28, $this->radius());
                $this->rect($cardX, $top + 14, $cardWidth, 14, '#000000', 0.35, 0);
                $this->line($cardX + 4, $top + 20, $cardWidth * 0.6, '#ffffff', 0.95, 1.6);
                $this->line($cardX + 4, $top + 24, $cardWidth * 0.75, '#ffffff', 0.6, 1.1);

                continue;
            }

            $this->card($cardX, $top, $cardWidth, 28);
            $this->photo($cardX, $top, $cardWidth, 12, 0);
            $this->line($cardX + 4, $top + 17, $cardWidth * 0.55, $this->ink, 0.8, 1.6);
            $this->line($cardX + 4, $top + 22, $cardWidth * 0.75, $this->ink, 0.4, 1.1);
        }
    }

    private function about(float $y): void
    {
        $layout = $this->design['about_layout'];
        $x0 = $this->x0 + 12;
        $width = $this->x1 - $this->x0 - 24;
        $paragraph = function (float $x, float $top, float $lineWidth): void {
            $this->line($x, $top, $lineWidth * 0.7, $this->ink, 0.85, 2.4);
            foreach ([0, 1, 2] as $index) {
                $this->line($x, $top + 6 + $index * 4, $lineWidth * ($index === 2 ? 0.6 : 0.95), $this->ink, 0.4, 1.1);
            }
        };

        match ($layout) {
            'banner' => [$this->photo($x0, $y + 6, $width, 16, $this->radius()), $paragraph($x0, $y + 28, $width * 0.7)],
            'overlap' => [$this->photo($x0 + $width * 0.38, $y + 6, $width * 0.62, 34, $this->radius()), $this->card($x0, $y + 12, $width * 0.48, 24, filled: true), $paragraph($x0 + 5, $y + 17, $width * 0.4)],
            'split_reverse' => [$this->photo($x0, $y + 8, $width / 2 - 6, 30, $this->radius()), $paragraph($x0 + $width / 2 + 6, $y + 14, $width / 2 - 6)],
            default => [$paragraph($x0, $y + 14, $width / 2 - 6), $this->photo($x0 + $width / 2 + 6, $y + 8, $width / 2 - 6, 30, $this->radius())],
        };
    }

    private function highlights(float $y): void
    {
        $style = $this->design['highlights_style'];
        $x0 = $this->x0 + 12;
        $width = $this->x1 - $this->x0 - 24;
        $ink = $this->ink;

        if ($style === 'band') {
            $this->rect($this->x0, $y, $this->x1 - $this->x0, 30, $this->palette['primary']);
            $ink = $this->palette['primary_ink'];
        }

        $columnWidth = ($width - 12) / 3;

        foreach ([0, 1, 2] as $index) {
            $x = $x0 + $index * ($columnWidth + 6);

            if ($style === 'boxes') {
                $this->card($x, $y + 5, $columnWidth, 20);
                $this->rect($x + 4, $y + 9, 5, 5, $this->soft, 1, 1);
                $this->line($x + 4, $y + 18, $columnWidth * 0.6, $ink, 0.8, 1.5);
                $this->line($x + 4, $y + 22, $columnWidth * 0.8, $ink, 0.4, 1.1);

                continue;
            }

            $this->rect($x, $y + 8, $columnWidth, 0.9, $style === 'band' ? $ink : $this->palette['primary']);
            $this->line($x, $y + 14, $columnWidth * 0.6, $ink, 0.85, 1.6);
            $this->line($x, $y + 19, $columnWidth * 0.9, $ink, 0.45, 1.1);
            $this->line($x, $y + 23, $columnWidth * 0.7, $ink, 0.45, 1.1);
        }
    }

    private function gallery(float $y): void
    {
        $style = $this->design['gallery_style'];
        $x0 = $this->x0 + 12;
        $width = $this->x1 - $this->x0 - 24;
        $gap = 3;
        $radius = $this->smallRadius();

        if ($style === 'mosaic') {
            $cell = ($width - 3 * $gap) / 4;
            $this->photo($x0, $y + 6, $cell * 2 + $gap, 32, $radius);
            foreach ([[2, 0], [3, 0], [2, 1], [3, 1]] as [$column, $row]) {
                $this->photo($x0 + $column * ($cell + $gap), $y + 6 + $row * 17.5, $cell, 14.5, $radius);
            }

            return;
        }

        $cell = ($width - 2 * $gap) / 3;
        $heights = $style === 'masonry' ? [[18, 13], [11, 20], [15, 16]] : [[14.5, 14.5], [14.5, 14.5], [14.5, 14.5]];

        foreach ($heights as $column => [$first, $second]) {
            $x = $x0 + $column * ($cell + $gap);
            $this->photo($x, $y + 6, $cell, $first, $radius);
            $this->photo($x, $y + 6 + $first + $gap, $cell, $second, $radius);
        }
    }

    private function cta(float $y): void
    {
        $style = $this->design['cta_style'];
        $x0 = $this->x0;
        $width = $this->x1 - $this->x0;

        if ($style === 'minimal') {
            $this->rect($x0 + 12, $y, $width - 24, 0.4, $this->ink, 0.2);
            $this->line($x0 + $width / 2 - 35, $y + 9, 70, $this->ink, 0.85, 2.4);
            $this->rect($x0 + $width / 2 - 14, $y + 15, 28, 7, $this->palette['primary'], 1, $this->buttonRadius());

            return;
        }

        [$bx, $by, $bw, $bh, $radius] = $style === 'card' ? [$x0 + 12, $y + 2, $width - 24, 22, $this->radius()] : [$x0, $y, $width, 28, 0];
        $this->rect($bx, $by, $bw, $bh, $this->palette['secondary'], 1, $radius);
        $this->line($bx + 10, $by + $bh / 2 - 2, 70, $this->palette['secondary_ink'], 0.9, 2.4);
        $this->line($bx + 10, $by + $bh / 2 + 3, 50, $this->palette['secondary_ink'], 0.5, 1.1);
        $this->rect($bx + $bw - 40, $by + $bh / 2 - 3.5, 28, 7, '#ffffff', 1, $this->buttonRadius());
    }

    private function footer(float $y): void
    {
        [$background, $ink] = match ($this->design['footer']) {
            'brand' => [$this->palette['secondary'], $this->palette['secondary_ink']],
            'light' => [$this->design['surface'] === 'dark' ? '#1a1d24' : '#f1f2f4', $this->ink],
            default => ['#111827', '#ffffff'],
        };
        $x0 = $this->x0;
        $width = $this->x1 - $this->x0;
        $this->rect($x0, $y, $width, $this->design['footer_layout'] === 'minimal' ? 12 : 26, $background);

        match ($this->design['footer_layout']) {
            'minimal' => [$this->line($x0 + 12, $y + 6, 30, $ink, 0.6, 1.1), $this->line($x0 + $width - 52, $y + 6, 40, $ink, 0.6, 1.1)],
            'centered' => [$this->line($x0 + $width / 2 - 20, $y + 8, 40, $ink, 0.9, 1.8), $this->line($x0 + $width / 2 - 30, $y + 13, 60, $ink, 0.5, 1.1), $this->line($x0 + $width / 2 - 22, $y + 19, 44, $ink, 0.5, 1.1)],
            default => array_map(fn (int $index) => [
                $this->line($x0 + 12 + $index * ($width - 24) / 3, $y + 8, 26, $ink, 0.9, 1.6),
                $this->line($x0 + 12 + $index * ($width - 24) / 3, $y + 13, 34, $ink, 0.5, 1.1),
                $this->line($x0 + 12 + $index * ($width - 24) / 3, $y + 17, 28, $ink, 0.5, 1.1),
            ], [0, 1, 2]),
        };
    }

    private function title(float $y): void
    {
        $width = 64;
        $x = $this->design['titles'] === 'centered' ? ($this->x0 + $this->x1 - $width) / 2 : $this->x0 + 12;
        $this->line($x, $y, $width, $this->ink, 0.9, 2.6);

        if ($this->design['titles'] === 'accent') {
            $this->line($x, $y + 4, 12, $this->palette['primary'], 1, 1.4);
        }
    }

    private function card(float $x, float $y, float $width, float $height, bool $filled = false): void
    {
        $fill = $this->design['surface'] === 'cream' ? '#fffdf9' : $this->bg;
        $stroke = $this->design['cards'] === 'elevated' && ! $filled ? 'none' : ($this->design['surface'] === 'dark' ? '#3a3f4a' : '#dfe3e8');
        $shadow = in_array($this->design['cards'], ['elevated'], true) || $filled
            ? sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="#000" opacity="0.08"/>', $this->n($x + 0.6), $this->n($y + 1.2), $this->n($width), $this->n($height), $this->n($this->radius()))
            : '';
        $this->shapes[] = $shadow.sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s" stroke="%s" stroke-width="0.5"/>',
            $this->n($x), $this->n($y), $this->n($width), $this->n($height), $this->n($this->radius()), $this->design['cards'] === 'flat' && ! $filled ? ($this->design['surface'] === 'dark' ? '#1d2027' : '#eef0f3') : $fill, $stroke,
        );

        if ($this->design['cards'] === 'accent' && ! $filled) {
            $this->rect($x, $y, 1.2, $height, $this->palette['primary']);
        }
    }

    private function photo(float $x, float $y, float $width, float $height, ?float $radius = null, bool $bottomOnly = false): void
    {
        $radius ??= $this->radius();
        $id = 'wf-clip-'.++self::$clipCount;
        $this->shapes[] = sprintf(
            '<clipPath id="%s"><rect x="%s" y="%s" width="%s" height="%s" rx="%s"/></clipPath><g clip-path="url(#%s)"><rect x="%s" y="%s" width="%s" height="%s" fill="%s"/><path d="M%s %s L%s %s L%s %s L%s %s L%s %s Z" fill="%s"/><circle cx="%s" cy="%s" r="%s" fill="#fff" opacity="0.7"/></g>',
            $id, $this->n($x), $this->n($y), $this->n($width), $this->n($height + ($bottomOnly ? $radius : 0)), $this->n($radius), $id,
            $this->n($x), $this->n($y), $this->n($width), $this->n($height), self::PHOTO,
            $this->n($x), $this->n($y + $height), $this->n($x + $width * 0.35), $this->n($y + $height * 0.45), $this->n($x + $width * 0.55), $this->n($y + $height * 0.7),
            $this->n($x + $width * 0.75), $this->n($y + $height * 0.35), $this->n($x + $width), $this->n($y + $height), self::PHOTO_DARK,
            $this->n($x + $width * 0.2), $this->n($y + $height * 0.3), $this->n(min($width, $height) * 0.1),
        );
    }

    private function heroBackground(): string
    {
        return match ($this->design['hero_background']) {
            'primary' => $this->palette['primary'],
            'dark' => '#111827',
            default => $this->soft,
        };
    }

    private function radius(): float
    {
        return ['none' => 0, 'small' => 1.5, 'medium' => 3, 'large' => 5][$this->design['radius']];
    }

    private function smallRadius(): float
    {
        return min($this->radius(), 2);
    }

    private function buttonRadius(): float
    {
        return ['pill' => 3.5, 'rounded' => 1.5, 'square' => 0][$this->design['buttons']];
    }

    private function rect(float $x, float $y, float $width, float $height, string $fill, float $opacity = 1, float $radius = 0): void
    {
        $this->shapes[] = sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" fill="%s"%s%s/>',
            $this->n($x), $this->n($y), $this->n($width), $this->n($height), $fill,
            $radius > 0 ? ' rx="'.$this->n($radius).'"' : '',
            $opacity < 1 ? ' opacity="'.$this->n($opacity).'"' : '',
        );
    }

    private function line(float $x, float $y, float $length, string $color, float $opacity, float $thickness): void
    {
        $this->rect($x, $y - $thickness / 2, max($length, 0), $thickness, $color, $opacity, $thickness / 2);
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
