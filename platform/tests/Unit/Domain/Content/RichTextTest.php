<?php

namespace Tests\Unit\Domain\Content;

use App\Domain\Content\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_keeps_allowed_blocks_and_marks(): void
    {
        $blocks = (new RichText([12]))->sanitize([
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Titre']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Gras ', 'marks' => [['type' => 'bold'], ['type' => 'textStyle', 'attrs' => ['color' => 'red']]]],
                ['type' => 'text', 'text' => 'lien', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'page:contact#formulaire', 'target' => '_blank']]]],
            ]],
            ['type' => 'mediaImage', 'attrs' => ['media' => 12, 'caption' => ' Chantier ', 'size' => 'wide']],
            ['type' => 'siteButton', 'attrs' => ['label' => 'Appeler', 'href' => 'tel:+33612345678', 'variant' => 'secondary']],
        ]);

        $this->assertSame(['level' => 3], $blocks[0]['attrs']);
        $this->assertSame([['type' => 'bold']], $blocks[1]['content'][0]['marks']);
        $this->assertSame([['type' => 'link', 'attrs' => ['href' => 'page:contact#formulaire']]], $blocks[1]['content'][1]['marks']);
        $this->assertSame(['media' => 12, 'caption' => 'Chantier', 'size' => 'wide'], $blocks[2]['attrs']);
        $this->assertSame('secondary', $blocks[3]['attrs']['variant']);
    }

    public function test_removes_unknown_nodes_foreign_media_and_unsafe_links(): void
    {
        $blocks = (new RichText([12]))->sanitize([
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => '<script>']]],
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Pas de H1']]],
            ['type' => 'mediaImage', 'attrs' => ['media' => 99]],
            ['type' => 'siteButton', 'attrs' => ['label' => 'Piège', 'href' => 'javascript:alert(1)']],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ]);

        $this->assertCount(2, $blocks);
        $this->assertSame(['level' => 2], $blocks[0]['attrs']);
        $this->assertArrayNotHasKey('marks', $blocks[1]['content'][0]);
    }

    public function test_columns_cannot_be_nested_and_need_two_columns(): void
    {
        $column = fn (array $content): array => ['type' => 'column', 'content' => $content];
        $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texte']]];

        $blocks = (new RichText)->sanitize([
            ['type' => 'columns', 'content' => [$column([$paragraph, ['type' => 'columns', 'content' => [$column([$paragraph]), $column([$paragraph])]]]), $column([])]],
            ['type' => 'columns', 'content' => [$column([$paragraph])]],
        ]);

        $this->assertCount(1, $blocks);
        $this->assertSame([$paragraph], $blocks[0]['content'][0]['content']);
        $this->assertSame([['type' => 'paragraph', 'content' => []]], $blocks[0]['content'][1]['content']);
    }

    public function test_accepts_only_safe_hrefs(): void
    {
        $this->assertSame('page:services', RichText::safeHref('page:services'));
        $this->assertSame('https://exemple.fr/a?b=1', RichText::safeHref(' https://exemple.fr/a?b=1 '));
        $this->assertSame('mailto:contact@exemple.fr', RichText::safeHref('mailto:contact@exemple.fr'));
        $this->assertSame('tel:+33612345678', RichText::safeHref('tel:+33612345678'));
        $this->assertNull(RichText::safeHref('javascript:alert(1)'));
        $this->assertNull(RichText::safeHref('page:../admin'));
        $this->assertNull(RichText::safeHref('//evil.test'));
        $this->assertNull(RichText::safeHref('https://exemple.fr/"onmouseover="x'));
    }

    public function test_extracts_plain_text_and_linked_pages(): void
    {
        $blocks = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Titre']]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Point', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'page:about']]]]]]]]]],
            ['type' => 'siteButton', 'attrs' => ['label' => 'Contact', 'href' => 'page:contact', 'variant' => 'primary']],
        ];

        $this->assertSame("Titre\n\n• Point\n\nContact", RichText::plainText($blocks));
        $this->assertSame(['about', 'contact'], RichText::linkedPages($blocks));
    }
}
