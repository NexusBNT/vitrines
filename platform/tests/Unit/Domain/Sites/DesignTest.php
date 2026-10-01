<?php

namespace Tests\Unit\Domain\Sites;

use App\Domain\Sites\Design;
use App\Domain\Sites\DesignWireframe;
use App\Domain\Sites\SiteTemplates;
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

    public function test_each_template_is_a_complete_valid_design(): void
    {
        foreach (array_keys(SiteTemplates::ALL) as $key) {
            $design = SiteTemplates::design($key);

            $this->assertEquals($design, Design::normalize($design, Design::fromStyle(null, '#000000')), "Modèle {$key}");
        }
    }

    public function test_template_colors_can_be_replaced_and_its_key_is_kept(): void
    {
        $design = Design::normalize(SiteTemplates::design('cocon', '#0f766e', null), Design::fromStyle(null, '#000000'));

        $this->assertSame('cocon', $design['template']);
        $this->assertSame('#0f766e', $design['primary']);
        $this->assertNull($design['secondary']);
        $this->assertStringContainsString('nav-centered', Design::bodyClasses($design));
    }

    public function test_existing_designs_keep_their_look_when_new_tokens_appear(): void
    {
        $design = Design::normalize(['header' => 'dark'], Design::fromStyle('premium', '#123456'));

        $this->assertSame('white', $design['surface']);
        $this->assertSame('top', $design['nav_layout']);
        $this->assertSame('cards', $design['services_style']);
        $this->assertArrayNotHasKey('template', $design);
    }

    public function test_themes_have_distinct_structures(): void
    {
        $structures = collect(SiteTemplates::ALL)->map(fn (array $template): string => json_encode($template['structure']));

        $this->assertCount(count(SiteTemplates::ALL), $structures->unique());
        $this->assertCount(count(Design::STRUCTURE['nav_layout']), collect(SiteTemplates::ALL)->pluck('structure.nav_layout')->unique(), 'Chaque navigation est utilisée par au moins un thème.');
        $this->assertGreaterThanOrEqual(5, collect(SiteTemplates::ALL)->pluck('structure.hero_layout')->unique()->count());
    }

    public function test_wireframe_draws_the_chosen_structure(): void
    {
        $full = DesignWireframe::svg(SiteTemplates::design('studio'));
        $focused = DesignWireframe::svg(SiteTemplates::design('atelier'), 'services_style');

        $this->assertStringStartsWith('<svg', $full);
        $this->assertStringContainsString('viewBox="0 0 ', $full);
        $this->assertStringNotContainsString('viewBox="0 0 ', $focused, 'La miniature cadrée commence à la section concernée.');
    }
}
