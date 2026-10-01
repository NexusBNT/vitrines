<?php

namespace App\Domain\Content;

/**
 * Sections qu'une page peut contenir : celles du thème, plus les blocs libres (« content »).
 */
final class SectionTypes
{
    public const LABELS = [
        'hero' => 'Bandeau d\'accueil',
        'page_header' => 'En-tête de page',
        'content' => 'Texte libre',
        'services' => 'Services',
        'about' => 'Présentation',
        'highlights' => 'Points forts',
        'gallery' => 'Galerie',
        'zone' => 'Zone d\'intervention',
        'faq' => 'Questions fréquentes',
        'cta' => 'Appel à l\'action',
        'contact' => 'Contact',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }
}
