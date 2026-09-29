<?php

namespace App\Domain\Sites;

use InvalidArgumentException;

/**
 * Dérive une palette accessible (WCAG AA) à partir de la couleur choisie par le client.
 */
final class ColorPalette
{
    private const WHITE = [255, 255, 255];

    private const INK = [17, 24, 39];

    /**
     * @return array{primary: string, primary_ink: string, primary_strong: string, primary_soft: string, secondary: string, secondary_ink: string}
     */
    public static function from(string $primary, ?string $secondary = null): array
    {
        $primaryRgb = self::parse($primary);
        $secondaryRgb = $secondary !== null && $secondary !== '' ? self::parse($secondary) : self::mix($primaryRgb, [0, 0, 0], 0.55);

        return [
            'primary' => self::hex($primaryRgb),
            'primary_ink' => self::hex(self::readableOn($primaryRgb)),
            'primary_strong' => self::hex(self::darkenUntilReadable($primaryRgb, self::WHITE, 4.5)),
            'primary_soft' => self::hex(self::mix($primaryRgb, self::WHITE, 0.92)),
            'secondary' => self::hex($secondaryRgb),
            'secondary_ink' => self::hex(self::readableOn($secondaryRgb)),
        ];
    }

    /**
     * Rapport de contraste WCAG entre deux couleurs (1 à 21).
     */
    public static function contrast(string $first, string $second): float
    {
        return self::contrastRgb(self::parse($first), self::parse($second));
    }

    /**
     * @return array{int, int, int}
     */
    private static function parse(string $hex): array
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $matches)) {
            throw new InvalidArgumentException("Couleur invalide : {$hex}");
        }

        return array_map(hexdec(...), str_split($matches[1], 2));
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    /**
     * @param  array{int, int, int}  $rgb
     * @return array{int, int, int}
     */
    private static function readableOn(array $rgb): array
    {
        return self::contrastRgb($rgb, self::WHITE) >= self::contrastRgb($rgb, self::INK) ? self::WHITE : self::INK;
    }

    /**
     * @param  array{int, int, int}  $rgb
     * @param  array{int, int, int}  $background
     * @return array{int, int, int}
     */
    private static function darkenUntilReadable(array $rgb, array $background, float $ratio): array
    {
        $candidate = $rgb;

        for ($step = 1; self::contrastRgb($candidate, $background) < $ratio && $step <= 20; $step++) {
            $candidate = self::mix($rgb, [0, 0, 0], $step * 0.05);
        }

        return $candidate;
    }

    /**
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     * @return array{int, int, int}
     */
    private static function mix(array $from, array $to, float $amount): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }

    /**
     * @param  array{int, int, int}  $first
     * @param  array{int, int, int}  $second
     */
    private static function contrastRgb(array $first, array $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function luminance(array $rgb): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
