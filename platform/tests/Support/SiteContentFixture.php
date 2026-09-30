<?php

namespace Tests\Support;

/**
 * Réponse d'IA valide pour un site dont le brief compte les services donnés.
 */
class SiteContentFixture
{
    /**
     * @param  list<string>  $services
     * @return array<string, mixed>
     */
    public static function valid(array $services, string $city = 'Rennes'): array
    {
        $page = fn (string $subject): array => [
            'title' => "{$subject} – Dupont Plomberie à {$city}",
            'meta_description' => "{$subject} de Dupont Plomberie à {$city} : découvrez comment nous intervenons chez vous et contactez-nous pour parler de votre projet.",
            'h1' => "{$subject} à {$city}",
            'lead' => "Tout savoir sur {$subject}.",
        ];

        return [
            'schema_type' => 'Plumber',
            'home' => [
                'title' => "Plombier chauffagiste à {$city} – Dupont Plomberie",
                'meta_description' => "Dupont Plomberie, plombier chauffagiste à {$city} : dépannage, installation et rénovation. Appelez-nous ou écrivez-nous pour votre projet.",
                'h1' => "Votre plombier chauffagiste à {$city}",
                'lead' => 'Dépannage, installation et rénovation pour les particuliers.',
                'services_heading' => 'Ce que nous faisons',
                'services_intro' => 'Des interventions soignées, du diagnostic à la finition.',
                'about_heading' => 'Un artisan de proximité',
                'about_paragraphs' => ['Nous intervenons chez les particuliers.'],
                'zone_heading' => 'Où intervenons-nous ?',
                'zone_text' => "Nous intervenons à {$city} et dans les communes voisines.",
                'cta_heading' => 'Parlons de votre projet',
                'cta_text' => 'Appelez-nous ou envoyez un message.',
            ],
            'services' => array_map(fn (string $name): array => [
                'name' => $name,
                'summary' => "Résumé : {$name}.",
                'details' => "Détail complet : {$name}.\n\nDeuxième paragraphe.",
            ], $services),
            'services_page' => $page('Nos services'),
            'about_page' => [...$page('À propos'), 'paragraphs' => ['Premier paragraphe.', 'Second paragraphe.']],
            'gallery_page' => [...$page('Nos réalisations'), 'intro' => 'Quelques chantiers récents.'],
            'contact_page' => [...$page('Contact'), 'text' => 'Décrivez-nous votre besoin.'],
            'highlights' => ['heading' => 'Nos engagements', 'items' => [
                ['title' => 'Proximité', 'text' => 'Un interlocuteur unique.'],
                ['title' => 'Clarté', 'text' => 'Des explications à chaque étape.'],
            ]],
            'faq' => ['heading' => 'Questions fréquentes', 'items' => [
                ['question' => 'Intervenez-vous autour de '.$city.' ?', 'answer' => 'Oui, dans les communes voisines.'],
                ['question' => 'Comment vous contacter ?', 'answer' => 'Par téléphone ou via le formulaire.'],
            ]],
            'suggestions' => ['Demander la date de création de l\'entreprise.'],
        ];
    }
}
