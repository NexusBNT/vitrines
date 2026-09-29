<?php

namespace Tests\Unit\Domain\Sites;

use App\Domain\Sites\ColorPalette;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColorPaletteTest extends TestCase
{
    #[DataProvider('colors')]
    public function test_derived_colors_meet_wcag_aa_contrast(string $primary): void
    {
        $palette = ColorPalette::from($primary);

        $this->assertGreaterThanOrEqual(4.5, ColorPalette::contrast($palette['primary_strong'], '#ffffff'));
        $this->assertGreaterThanOrEqual(3.0, ColorPalette::contrast($palette['primary_ink'], $palette['primary']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function colors(): array
    {
        return [
            'light yellow' => ['#facc15'],
            'mid orange' => ['#f97316'],
            'dark blue' => ['#1e3a8a'],
            'light green' => ['#86efac'],
        ];
    }

    public function test_keeps_an_already_readable_primary_unchanged(): void
    {
        $this->assertSame('#1e3a8a', ColorPalette::from('#1E3A8A')['primary_strong']);
    }

    public function test_uses_dark_text_on_a_light_primary(): void
    {
        $this->assertSame('#111827', ColorPalette::from('#facc15')['primary_ink']);
    }

    public function test_rejects_a_value_that_is_not_a_hex_color(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColorPalette::from('red;}body{display:none');
    }
}
