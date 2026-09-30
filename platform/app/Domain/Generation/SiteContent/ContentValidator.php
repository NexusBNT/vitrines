<?php

namespace App\Domain\Generation\SiteContent;

use Illuminate\Support\Str;

/**
 * Vérifie les textes rédigés par l'IA avant qu'ils n'atteignent le site.
 *
 * Erreurs : bloquantes (l'IA est relancée avec la liste à corriger).
 * Avertissements : affichés à l'éditeur, qui décide.
 */
class ContentValidator
{
    /**
     * Affirmations qu'un texte ne peut contenir que si le brief les mentionne déjà.
     */
    private const CLAIMS = [
        'une année' => '/\b(19[5-9]\d|20[0-4]\d)\b/u',
        'un nombre d\'années d\'expérience' => '/\b\d+\s*ans?\s+d[\'’]\s*(expérience|existence)/iu',
        'une garantie décennale' => '/décennale/iu',
        'une certification ou un label' => '/\b(rge|qualibat|qualifelec|qualigaz|qualipac|qualibois|qualisol|certifi\w*|labellis\w*|agréé\w*)\b/iu',
        'un prix ou un tarif' => '/(\d\s*€|\beuros?\b|\btarifs?\b|\bprix\b)/iu',
        'la gratuité' => '/\bgratuit\w*/iu',
        'une disponibilité 24h/24 ou 7j/7' => '/(24\s*h?\s*\/\s*24|7\s*j(ours)?\s*\/\s*7|jour et nuit)/iu',
        'un délai d\'intervention chiffré' => '/\b(en|sous|moins de)\s+\d+\s*(h\b|heures?|minutes?|min\b|jours?)/iu',
        'un classement ou superlatif' => '/(\bn°\s*1\b|numéro un|\bleader\b|\ble meilleur\b|\bla meilleure\b|\bmeilleurs\b)/iu',
        'des avis ou une note' => '/(\bavis\s+(clients?|google)\b|\bétoiles\b|\bnote de \d)/iu',
        'un nombre de clients ou de chantiers' => '/\b\d[\d\s.]*\s+(clients|chantiers|projets|interventions|réalisations)\b/iu',
    ];

    /**
     * @param  array<string, mixed>  $content  Réponse de l'IA (conforme à ContentSchema)
     * @param  array<string, mixed>  $brief
     * @param  list<string>  $pageKeys  Pages présentes sur le site
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(array $content, array $brief, array $pageKeys): array
    {
        $errors = [];
        $warnings = [];

        $briefServices = array_values(array_filter(array_map(fn (array $service): string => trim($service['name'] ?? ''), $brief['services'] ?? [])));

        if (count($content['services']) !== count($briefServices)) {
            $errors[] = sprintf('Il faut exactement %d services, dans l\'ordre fourni (reçu : %d).', count($briefServices), count($content['services']));
        }

        $titles = [];

        foreach ($this->pages($content, $pageKeys) as $label => $page) {
            $titleLength = mb_strlen(trim($page['title']));
            $descriptionLength = mb_strlen(trim($page['meta_description']));

            if ($titleLength < 20 || $titleLength > 70) {
                $errors[] = "{$label} : le title fait {$titleLength} caractères (30 à 65 attendus).";
            }

            if ($descriptionLength < 70 || $descriptionLength > 170) {
                $errors[] = "{$label} : la meta description fait {$descriptionLength} caractères (120 à 155 attendus).";
            }

            if (mb_strlen(trim($page['h1'])) > 90 || trim($page['h1']) === '') {
                $errors[] = "{$label} : le h1 doit être non vide et faire 70 caractères au maximum.";
            }

            $titles[] = Str::lower(trim($page['title']));
        }

        if (count($titles) !== count(array_unique($titles))) {
            $errors[] = 'Chaque page doit avoir un title différent.';
        }

        $text = $this->allText($content);

        if (preg_match('/<\/?[a-z][^>]*>|\*\*|^#+\s/imu', $text)) {
            $errors[] = 'Le texte doit être brut : pas de HTML ni de Markdown.';
        }

        $facts = $this->briefText($brief);

        foreach (self::CLAIMS as $label => $pattern) {
            if (preg_match($pattern, $text, $match) && ! preg_match($pattern, $facts)) {
                $errors[] = "Le texte affirme {$label} (« {$match[0]} ») alors que cette information n'est pas fournie : retire-la.";
            }
        }

        $city = Str::lower(Str::ascii($brief['city'] ?? ''));

        if ($city !== '' && ! str_contains(Str::lower(Str::ascii($content['home']['title'].' '.$content['home']['h1'])), $city)) {
            $warnings[] = 'La ville principale n\'apparaît ni dans le title ni dans le titre de la page d\'accueil.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  list<string>  $pageKeys
     * @return array<string, array<string, mixed>>
     */
    private function pages(array $content, array $pageKeys): array
    {
        $map = [
            'home' => ['Accueil', 'home'],
            'services' => ['Services', 'services_page'],
            'about' => ['À propos', 'about_page'],
            'gallery' => ['Réalisations', 'gallery_page'],
            'contact' => ['Contact', 'contact_page'],
        ];

        $pages = [];

        foreach ($pageKeys as $key) {
            if (isset($map[$key])) {
                [$label, $field] = $map[$key];
                $pages[$label] = $content[$field];
            }
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function allText(array $content): string
    {
        $content = collect($content)->except(['schema_type', 'suggestions'])->all();
        $parts = [];

        array_walk_recursive($content, function (mixed $value) use (&$parts): void {
            if (is_string($value)) {
                $parts[] = $value;
            }
        });

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function briefText(array $brief): string
    {
        $services = collect($brief['services'] ?? [])->map(fn (array $service): string => ($service['name'] ?? '').' '.($service['description'] ?? ''))->implode("\n");

        return implode("\n", [
            $brief['business_name'] ?? '',
            $brief['activity'] ?? '',
            $brief['description'] ?? '',
            $services,
            $brief['opening_hours_note'] ?? '',
            $brief['notes'] ?? '',
        ]);
    }
}
