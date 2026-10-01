<?php

namespace App\Domain\Content;

/**
 * Texte brut d'une page (toutes sections confondues), pour l'IA et la comparaison de versions.
 */
final class PageText
{
    /**
     * @param  array<string, mixed>  $page
     */
    public static function of(array $page): string
    {
        return collect($page['sections'] ?? [])
            ->map(fn (array $section): string => self::section($section))
            ->filter(fn (string $text): bool => trim($text) !== '')
            ->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $section
     */
    public static function section(array $section): string
    {
        if ($section['type'] === 'content') {
            return RichText::plainText($section['blocks'] ?? []);
        }

        $parts = [];

        foreach (['h1', 'heading', 'lead', 'intro', 'text'] as $field) {
            if (is_string($section[$field] ?? null)) {
                $parts[] = $section[$field];
            }
        }

        foreach ($section['paragraphs'] ?? [] as $paragraph) {
            $parts[] = $paragraph;
        }

        foreach ($section['items'] ?? [] as $item) {
            $parts[] = collect([$item['name'] ?? null, $item['title'] ?? null, $item['question'] ?? null, $item['text'] ?? null, $item['answer'] ?? null])->filter()->implode("\n");
        }

        if (! empty($section['towns'])) {
            $parts[] = implode(', ', $section['towns']);
        }

        return implode("\n\n", array_filter($parts, fn (string $part): bool => trim($part) !== ''));
    }
}
