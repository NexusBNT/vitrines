<?php

namespace App\Domain\Content;

/**
 * Passage entre les pages de la spécification (chemin complet) et celles de l'éditeur (segment + parent).
 */
final class PageTree
{
    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<array<string, mixed>>
     */
    public static function toEditor(array $pages): array
    {
        return array_map(fn (array $page): array => [
            'key' => $page['key'],
            'segment' => $page['slug'] === '' ? '' : basename($page['slug']),
            'parent' => $page['parent'] ?? null,
            'nav_label' => $page['nav_label'],
            'in_nav' => $page['in_nav'] ?? true,
            'title' => $page['title'],
            'meta_description' => $page['meta_description'],
            'noindex' => $page['noindex'] ?? false,
            'sections' => $page['sections'],
        ], $pages);
    }

    /**
     * Après une nouvelle rédaction complète, garde les pages créées dans l'éditeur
     * (absentes de la structure standard), dans la limite de l'offre.
     *
     * @param  array<string, mixed>|null  $previous  Spécification remplacée
     * @param  array<string, mixed>  $spec  Nouvelle spécification
     * @return array<string, mixed>
     */
    public static function carryOverCustomPages(?array $previous, array $spec, int $maxPages): array
    {
        if ($previous === null) {
            return $spec;
        }

        $keys = array_column($spec['pages'], 'key');
        $slugs = array_column($spec['pages'], 'slug');

        foreach ($previous['pages'] as $page) {
            if (count($spec['pages']) >= $maxPages) {
                break;
            }

            if (in_array($page['key'], $keys, true) || in_array($page['slug'], $slugs, true)) {
                continue;
            }

            if (($page['parent'] ?? null) !== null && ! in_array($page['parent'], $keys, true)) {
                $page['parent'] = null;
                $page['slug'] = basename($page['slug']);

                if (in_array($page['slug'], $slugs, true)) {
                    continue;
                }
            }

            $spec['pages'][] = $page;
            $keys[] = $page['key'];
            $slugs[] = $page['slug'];
        }

        return $spec;
    }
}
