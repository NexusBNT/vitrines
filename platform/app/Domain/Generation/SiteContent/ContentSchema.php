<?php

namespace App\Domain\Generation\SiteContent;

/**
 * JSON Schema de la réponse attendue pour la rédaction d'un site.
 * Compatible avec les sorties structurées strictes de Claude et d'OpenAI :
 * tous les champs sont obligatoires et aucun champ supplémentaire n'est permis.
 */
class ContentSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];

        $page = fn (array $extra = []): array => self::object([
            'title' => $string,
            'meta_description' => $string,
            'h1' => $string,
            'lead' => $string,
            ...$extra,
        ]);

        return self::object([
            'schema_type' => ['type' => 'string', 'enum' => self::schemaTypes()],
            'home' => $page([
                'services_heading' => $string,
                'services_intro' => $string,
                'about_heading' => $string,
                'about_paragraphs' => $strings,
                'zone_heading' => $string,
                'zone_text' => $string,
                'cta_heading' => $string,
                'cta_text' => $string,
            ]),
            'services' => ['type' => 'array', 'items' => self::object([
                'name' => $string,
                'summary' => $string,
                'details' => $string,
            ])],
            'services_page' => $page(),
            'about_page' => $page(['paragraphs' => $strings]),
            'gallery_page' => $page(['intro' => $string]),
            'contact_page' => $page(['text' => $string]),
            'highlights' => self::object([
                'heading' => $string,
                'items' => ['type' => 'array', 'items' => self::object(['title' => $string, 'text' => $string])],
            ]),
            'faq' => self::object([
                'heading' => $string,
                'items' => ['type' => 'array', 'items' => self::object(['question' => $string, 'answer' => $string])],
            ]),
            'suggestions' => $strings,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function schemaTypes(): array
    {
        return array_values(array_unique(['LocalBusiness', ...array_values(config('vitrines.schema_types'))]));
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
