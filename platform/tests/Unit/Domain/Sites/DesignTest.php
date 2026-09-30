<?php

namespace Tests\Unit\Domain\Sites;

use App\Domain\Sites\Design;
use Tests\TestCase;

class DesignTest extends TestCase
{
    public function test_replaces_unknown_values_with_the_fallback(): void
    {
        $fallback = Design::fromStyle('moderne', '#0f766e');

        $design = Design::normalize([
            'primary' => 'red;}body{display:none',
            'font_pair' => 'comic-sans',
            'radius' => 'large',
            'header' => '"><script>',
        ], $fallback);

        $this->assertSame('#0f766e', $design['primary']);
        $this->assertSame('moderne', $design['font_pair']);
        $this->assertSame('large', $design['radius']);
        $this->assertSame('light', $design['header']);
    }

    public function test_each_brief_style_maps_to_a_complete_valid_design(): void
    {
        foreach (['sobre', 'chaleureux', 'moderne', 'premium', null] as $style) {
            $design = Design::fromStyle($style, '#123456');

            $this->assertEquals($design, Design::normalize($design, $design), "Style {$style}");
        }
    }

    public function test_system_fonts_embed_nothing_and_pairs_embed_both_families(): void
    {
        $this->assertSame([], Design::fonts(Design::fromStyle('sobre', '#123456')));
        $this->assertSame(['lora', 'nunito'], Design::fonts(Design::fromStyle('chaleureux', '#123456')));
    }

    public function test_body_classes_reflect_the_structural_tokens(): void
    {
        $classes = Design::bodyClasses(Design::fromStyle('premium', '#123456'));

        $this->assertStringContainsString('hdr-dark', $classes);
        $this->assertStringContainsString('headings-uppercase', $classes);
        $this->assertStringContainsString('btn-square', $classes);
    }
}
