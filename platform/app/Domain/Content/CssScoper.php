<?php

namespace App\Domain\Content;

/**
 * Restreint une feuille de style de thème à un conteneur (le canevas de l'éditeur),
 * pour afficher les pages aux couleurs du site sans toucher à l'interface autour.
 *
 * :root, html et body deviennent le conteneur ; les classes de design posées sur <body>
 * sur le site (btn-pill, hdr-dark…) s'appliquent au conteneur lui-même.
 */
final class CssScoper
{
    private const BODY_CLASS = '/^\.(btn|shadow|alt|headings|hdr|hero-bg|cards|ftr|nav|topbar|overlay|hero-align|svc|about|hl|gal|action|fl|pl|surface|titles)-[a-z-]+/';

    private const GROUPING_RULES = ['@media', '@supports', '@container', '@layer'];

    public static function scope(string $css, string $scope): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        return self::rules($css, $scope);
    }

    private static function rules(string $css, string $scope): string
    {
        $output = '';
        $length = strlen($css);
        $position = 0;

        while ($position < $length) {
            $open = strpos($css, '{', $position);
            $semicolon = strpos($css, ';', $position);

            if ($open === false) {
                break;
            }

            if ($semicolon !== false && $semicolon < $open) {
                $position = $semicolon + 1;

                continue;
            }

            $prelude = trim(substr($css, $position, $open - $position));
            $close = self::matchingBrace($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $position = $close + 1;

            if (str_starts_with($prelude, '@')) {
                $isGrouping = collect(self::GROUPING_RULES)->contains(fn (string $rule): bool => str_starts_with($prelude, $rule));
                $output .= $prelude.'{'.($isGrouping ? self::rules($body, $scope) : $body).'}';

                continue;
            }

            $selectors = array_map(fn (string $selector): string => self::selector(trim($selector), $scope), self::splitSelectors($prelude));
            $output .= implode(',', array_unique($selectors)).'{'.trim($body).'}';
        }

        return $output;
    }

    private static function selector(string $selector, string $scope): string
    {
        if (in_array($selector, [':root', 'html', 'body'], true)) {
            return $scope;
        }

        if (preg_match('/^(html|body)(?=[\s.:#\[>+~])/', $selector)) {
            return $scope.substr($selector, 4);
        }

        if (preg_match(self::BODY_CLASS, $selector)) {
            return $scope.$selector;
        }

        return $scope.' '.$selector;
    }

    /**
     * @return list<string>
     */
    private static function splitSelectors(string $prelude): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($prelude) as $character) {
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            }

            if ($character === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $parts[] = $current;

        return array_values(array_filter(array_map(trim(...), $parts), fn (string $part): bool => $part !== ''));
    }

    private static function matchingBrace(string $css, int $open): int
    {
        $depth = 0;
        $length = strlen($css);

        for ($index = $open; $index < $length; $index++) {
            if ($css[$index] === '{') {
                $depth++;
            } elseif ($css[$index] === '}' && --$depth === 0) {
                return $index;
            }
        }

        return $length - 1;
    }
}
