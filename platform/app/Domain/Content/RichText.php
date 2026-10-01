<?php

namespace App\Domain\Content;

use App\Domain\Build\RenderContext;
use Illuminate\Support\HtmlString;

/**
 * Blocs libres d'une page (section « content ») : sous-ensemble fermé du format JSON de l'éditeur (ProseMirror).
 *
 * Tout ce qui n'est pas dans la liste blanche est retiré à l'enregistrement ; le rendu échappe chaque texte.
 * Les liens internes sont stockés sous la forme « page:clé » (ou « page:clé#ancre ») pour survivre aux changements d'URL.
 */
class RichText
{
    public const MAX_NODES = 3000;

    private const MAX_TEXT = 4000;

    private const INLINE_MARKS = ['bold' => 'strong', 'italic' => 'em', 'underline' => 'u', 'strike' => 's'];

    private const CALLOUT_TONES = ['info', 'success', 'warning'];

    private int $nodeCount = 0;

    /**
     * @param  list<int>  $mediaIds  Médias autorisés (ceux du site)
     */
    public function __construct(private array $mediaIds = []) {}

    /**
     * @param  mixed  $blocks  Contenu reçu de l'éditeur (liste de nœuds)
     * @return list<array<string, mixed>>
     */
    public function sanitize(mixed $blocks): array
    {
        $this->nodeCount = 0;

        return $this->blocks($blocks, allowColumns: true, depth: 0);
    }

    /**
     * Texte brut (paragraphes séparés par une ligne vide), pour l'IA, les comparaisons et les contrôles.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function plainText(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $node) {
            $text = match ($node['type']) {
                'paragraph', 'heading' => self::inlineText($node['content'] ?? []),
                'bulletList', 'orderedList' => collect($node['content'] ?? [])->map(fn (array $item): string => '• '.self::plainText($item['content'] ?? []))->implode("\n"),
                'blockquote', 'callout', 'column' => self::plainText($node['content'] ?? []),
                'columns' => collect($node['content'] ?? [])->map(fn (array $column): string => self::plainText($column['content'] ?? []))->implode("\n\n"),
                'mediaImage' => (string) ($node['attrs']['caption'] ?? ''),
                'siteButton' => (string) ($node['attrs']['label'] ?? ''),
                default => '',
            };

            if (trim($text) !== '') {
                $parts[] = trim($text);
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Pages visées par des liens internes (pour détecter les liens vers une page supprimée).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    public static function linkedPages(array $blocks): array
    {
        $pages = [];

        array_walk_recursive($blocks, function (mixed $value, string|int $key) use (&$pages): void {
            if ($key === 'href' && is_string($value) && preg_match('/^page:([a-z0-9-]+)/', $value, $match)) {
                $pages[] = $match[1];
            }
        });

        return array_values(array_unique($pages));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function render(array $blocks, RenderContext $context): HtmlString
    {
        return new HtmlString(implode("\n", array_map(fn (array $node): string => self::renderNode($node, $context), $blocks)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(mixed $nodes, bool $allowColumns, int $depth): array
    {
        if (! is_array($nodes) || $depth > 6) {
            return [];
        }

        $clean = [];

        foreach (array_values($nodes) as $node) {
            if (! is_array($node) || ++$this->nodeCount > self::MAX_NODES) {
                continue;
            }

            $block = $this->block($node, $allowColumns, $depth);

            if ($block !== null) {
                $clean[] = $block;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function block(array $node, bool $allowColumns, int $depth): ?array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        switch ($node['type'] ?? null) {
            case 'paragraph':
                return ['type' => 'paragraph', 'content' => $this->inline($node['content'] ?? [])];

            case 'heading':
                $content = $this->inline($node['content'] ?? []);

                return $content === [] ? null : ['type' => 'heading', 'attrs' => ['level' => ($attrs['level'] ?? 2) == 3 ? 3 : 2], 'content' => $content];

            case 'bulletList':
            case 'orderedList':
                $items = [];

                foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $item) {
                    if (is_array($item) && ($item['type'] ?? null) === 'listItem') {
                        $content = $this->blocks($item['content'] ?? [], allowColumns: false, depth: $depth + 1);
                        $items[] = ['type' => 'listItem', 'content' => $content === [] ? [['type' => 'paragraph', 'content' => []]] : $content];
                    }
                }

                if ($items === []) {
                    return null;
                }

                $list = ['type' => $node['type'], 'content' => $items];

                if ($node['type'] === 'orderedList' && (int) ($attrs['start'] ?? 1) > 1) {
                    $list['attrs'] = ['start' => min(999, (int) $attrs['start'])];
                }

                return $list;

            case 'blockquote':
                $content = $this->blocks($node['content'] ?? [], allowColumns: false, depth: $depth + 1);

                return $content === [] ? null : ['type' => 'blockquote', 'content' => $content];

            case 'callout':
                $content = $this->blocks($node['content'] ?? [], allowColumns: false, depth: $depth + 1);
                $tone = in_array($attrs['tone'] ?? null, self::CALLOUT_TONES, true) ? $attrs['tone'] : 'info';

                return $content === [] ? null : ['type' => 'callout', 'attrs' => ['tone' => $tone], 'content' => $content];

            case 'horizontalRule':
                return ['type' => 'horizontalRule'];

            case 'mediaImage':
                $media = (int) ($attrs['media'] ?? 0);

                if (! in_array($media, $this->mediaIds, true)) {
                    return null;
                }

                return ['type' => 'mediaImage', 'attrs' => [
                    'media' => $media,
                    'caption' => $this->text($attrs['caption'] ?? null, 300),
                    'size' => ($attrs['size'] ?? null) === 'wide' ? 'wide' : 'normal',
                ]];

            case 'siteButton':
                $label = $this->text($attrs['label'] ?? null, 60);
                $href = self::safeHref($attrs['href'] ?? null);

                if ($label === null || $href === null) {
                    return null;
                }

                return ['type' => 'siteButton', 'attrs' => [
                    'label' => $label,
                    'href' => $href,
                    'variant' => ($attrs['variant'] ?? null) === 'secondary' ? 'secondary' : 'primary',
                ]];

            case 'columns':
                if (! $allowColumns) {
                    return null;
                }

                $columns = [];

                foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $column) {
                    if (is_array($column) && ($column['type'] ?? null) === 'column') {
                        $content = $this->blocks($column['content'] ?? [], allowColumns: false, depth: $depth + 1);
                        $columns[] = ['type' => 'column', 'content' => $content === [] ? [['type' => 'paragraph', 'content' => []]] : $content];
                    }
                }

                $columns = array_slice($columns, 0, 3);

                return count($columns) < 2 ? null : ['type' => 'columns', 'content' => $columns];

            default:
                return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inline(mixed $nodes): array
    {
        if (! is_array($nodes)) {
            return [];
        }

        $clean = [];

        foreach (array_values($nodes) as $node) {
            if (! is_array($node) || ++$this->nodeCount > self::MAX_NODES) {
                continue;
            }

            if (($node['type'] ?? null) === 'hardBreak') {
                $clean[] = ['type' => 'hardBreak'];

                continue;
            }

            if (($node['type'] ?? null) !== 'text' || ! is_string($node['text'] ?? null) || $node['text'] === '') {
                continue;
            }

            $text = ['type' => 'text', 'text' => mb_substr(str_replace(["\r", "\0"], '', $node['text']), 0, self::MAX_TEXT)];
            $marks = $this->marks($node['marks'] ?? []);

            if ($marks !== []) {
                $text['marks'] = $marks;
            }

            $clean[] = $text;
        }

        return $clean;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marks(mixed $marks): array
    {
        if (! is_array($marks)) {
            return [];
        }

        $clean = [];

        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (isset(self::INLINE_MARKS[$type])) {
                $clean[$type] = ['type' => $type];
            } elseif ($type === 'link' && ($href = self::safeHref($mark['attrs']['href'] ?? null)) !== null) {
                $clean['link'] = ['type' => 'link', 'attrs' => ['href' => $href]];
            }
        }

        return array_values($clean);
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }

    /**
     * Lien autorisé : page interne, http(s), mailto ou tel.
     */
    public static function safeHref(mixed $href): ?string
    {
        if (! is_string($href)) {
            return null;
        }

        $href = trim($href);

        return match (true) {
            (bool) preg_match('/^page:[a-z0-9][a-z0-9-]{0,39}(#[a-z0-9][a-z0-9-]{0,39})?$/', $href) => $href,
            (bool) preg_match('/^https?:\/\/[^\s"<>]+$/i', $href) && filter_var($href, FILTER_VALIDATE_URL) !== false && mb_strlen($href) <= 500 => $href,
            (bool) preg_match('/^mailto:[^\s@"<>]+@[^\s@"<>]+\.[^\s@"<>]+$/i', $href) => $href,
            (bool) preg_match('/^tel:\+?\d{6,15}$/', $href) => $href,
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function inlineText(array $nodes): string
    {
        return collect($nodes)->map(fn (array $node): string => $node['type'] === 'hardBreak' ? "\n" : ($node['text'] ?? ''))->implode('');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function renderNode(array $node, RenderContext $context): string
    {
        $children = fn (): string => implode('', array_map(fn (array $child): string => self::renderNode($child, $context), $node['content'] ?? []));
        $attrs = $node['attrs'] ?? [];

        switch ($node['type']) {
            case 'paragraph':
                $inline = self::renderInline($node['content'] ?? [], $context);

                return $inline === '' ? '' : '<p>'.$inline.'</p>';

            case 'heading':
                $level = $attrs['level'] === 3 ? 3 : 2;

                return "<h{$level}>".self::renderInline($node['content'], $context)."</h{$level}>";

            case 'bulletList':
                return '<ul>'.$children().'</ul>';

            case 'orderedList':
                return '<ol'.(isset($attrs['start']) ? ' start="'.(int) $attrs['start'].'"' : '').'>'.$children().'</ol>';

            case 'listItem':
                return '<li>'.$children().'</li>';

            case 'blockquote':
                return '<blockquote>'.$children().'</blockquote>';

            case 'callout':
                return '<aside class="rt-callout rt-callout--'.e($attrs['tone']).'">'.$children().'</aside>';

            case 'horizontalRule':
                return '<hr>';

            case 'columns':
                return '<div class="rt-columns rt-columns--'.count($node['content']).'">'.$children().'</div>';

            case 'column':
                return '<div class="rt-column">'.$children().'</div>';

            case 'mediaImage':
                $media = $context->media($attrs['media']);

                if ($media === null) {
                    return '';
                }

                $wide = $attrs['size'] === 'wide';
                $caption = filled($attrs['caption']) ? '<figcaption>'.e($attrs['caption']).'</figcaption>' : '';

                return '<figure class="rt-figure'.($wide ? ' rt-figure--wide' : '').'">'
                    .$context->picture($attrs['media'], $wide ? '(min-width: 1200px) 1160px, 100vw' : '(min-width: 800px) 760px, 100vw')
                    .$caption.'</figure>';

            case 'siteButton':
                $href = self::resolveHref($attrs['href'], $context);

                if ($href === null) {
                    return '';
                }

                return '<p class="rt-button"><a class="button button-'.($attrs['variant'] === 'secondary' ? 'secondary' : 'primary').'" href="'.e($href).'"'
                    .self::linkExtras($attrs['href']).'>'.e($attrs['label']).'</a></p>';

            default:
                return '';
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function renderInline(array $nodes, RenderContext $context): string
    {
        $html = '';

        foreach ($nodes as $node) {
            if ($node['type'] === 'hardBreak') {
                $html .= '<br>';

                continue;
            }

            $text = e($node['text']);
            $link = null;

            foreach ($node['marks'] ?? [] as $mark) {
                if ($mark['type'] === 'link') {
                    $link = $mark['attrs']['href'];
                } elseif (isset(self::INLINE_MARKS[$mark['type']])) {
                    $tag = self::INLINE_MARKS[$mark['type']];
                    $text = "<{$tag}>{$text}</{$tag}>";
                }
            }

            if ($link !== null && ($href = self::resolveHref($link, $context)) !== null) {
                $text = '<a href="'.e($href).'"'.self::linkExtras($link).'>'.$text.'</a>';
            }

            $html .= $text;
        }

        return $html;
    }

    /**
     * URL finale d'un lien, ou null si la page visée n'existe plus.
     */
    private static function resolveHref(string $href, RenderContext $context): ?string
    {
        if (preg_match('/^page:([a-z0-9-]+)(?:#([a-z0-9-]+))?$/', $href, $match)) {
            return $context->hasPage($match[1]) ? $context->url($match[1], $match[2] ?? null) : null;
        }

        return $context->phoneHref(str_starts_with($href, 'tel:') ? substr($href, 4) : null) ?? $href;
    }

    private static function linkExtras(string $href): string
    {
        if (str_starts_with($href, 'tel:')) {
            return ' data-track="tel"';
        }

        if (str_starts_with($href, 'mailto:')) {
            return ' data-track="mail"';
        }

        return preg_match('/^https?:/i', $href) ? ' rel="noopener" target="_blank"' : '';
    }
}
