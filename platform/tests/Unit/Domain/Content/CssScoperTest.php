<?php

namespace Tests\Unit\Domain\Content;

use App\Domain\Content\CssScoper;
use PHPUnit\Framework\TestCase;

class CssScoperTest extends TestCase
{
    public function test_scopes_every_rule_to_the_canvas(): void
    {
        $css = CssScoper::scope(<<<'CSS'
            /* Thème */
            :root { --text: #111; }
            body { margin: 0; }
            h1, .card:has(.card-media) { color: red; }
            @media (min-width: 900px) { .split, html .x { display: grid; } }
            @font-face { font-family: "X"; src: url(x.woff2); }
            .hdr-dark .site-header { background: #000; }
            CSS, '.site-canvas');

        $this->assertStringContainsString('.site-canvas{--text: #111;}', $css);
        $this->assertStringContainsString('.site-canvas{margin: 0;}', $css);
        $this->assertStringContainsString('.site-canvas h1,.site-canvas .card:has(.card-media){color: red;}', $css);
        $this->assertStringContainsString('@media (min-width: 900px){.site-canvas .split,.site-canvas .x{display: grid;}}', $css);
        $this->assertStringContainsString('@font-face{ font-family: "X"; src: url(x.woff2); }', $css);
        $this->assertStringContainsString('.site-canvas.hdr-dark .site-header{background: #000;}', $css);
        $this->assertStringNotContainsString('Thème', $css);
    }
}
