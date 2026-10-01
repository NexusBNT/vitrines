<?php

namespace App\Domain\Generation\Editor;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\SiteContent\ContentValidator;
use App\Enums\SiteStyle;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * IA dans l'éditeur de pages : réécriture d'un passage, rédaction de blocs, title et meta description.
 *
 * Chaque réponse passe par le contrôle anti-invention : une affirmation sensible (prix, label, délai…)
 * n'est acceptée que si elle figure dans le brief, dans le texte d'origine ou sur la page.
 */
class EditorAssistant
{
    public const ACTIONS = [
        'improve' => 'Améliore la clarté, le rythme et le style de ce texte, sans en changer le sens ni la longueur de manière notable.',
        'shorten' => 'Raccourcis ce texte d\'environ un tiers en gardant l\'essentiel.',
        'lengthen' => 'Développe légèrement ce texte (une à deux phrases de plus) en explicitant ce qui est déjà dit, sans ajouter aucun fait nouveau.',
        'simplify' => 'Simplifie ce texte : mots courants, phrases courtes, compréhensible par tous.',
        'fix' => 'Corrige uniquement l\'orthographe, la grammaire, la ponctuation et la typographie française. Ne change rien d\'autre.',
        'warmer' => 'Réécris ce texte sur un ton plus chaleureux et proche, sans familiarité.',
        'professional' => 'Réécris ce texte sur un ton plus professionnel et rassurant.',
        'custom' => 'Réécris ce texte en suivant la consigne de l\'équipe.',
    ];

    private const MAX_ATTEMPTS = 2;

    public function __construct(private AiManager $ai, private ContentValidator $validator) {}

    public function isAvailable(): bool
    {
        return $this->ai->isAvailable('editor');
    }

    /**
     * @throws AiException
     */
    public function transform(Site $site, string $action, string $text, ?string $instruction = null, string $pageText = ''): string
    {
        $task = self::ACTIONS[$action] ?? throw new AiException('Action inconnue.');

        if ($action === 'custom' && blank($instruction)) {
            throw new AiException('Précisez la consigne.');
        }

        $prompt = $this->context($site, $pageText)
            ."\n\n## Demande\n{$task}"
            .(filled($instruction) ? "\nConsigne de l'équipe : ".trim($instruction) : '')
            ."\nConserve les retours à la ligne pertinents (paragraphes séparés par une ligne vide). Réponds dans « text » avec le texte réécrit seul."
            ."\n\n## Texte à traiter\n".$text;

        $data = $this->generate($site, 'editor_transform', $prompt, self::object(['text' => ['type' => 'string']]), $text."\n".$pageText,
            ['action' => $action, 'instruction' => $instruction, 'text' => Str::limit($text, 2000)],
            fn (array $data): array => $this->textProblems($data['text']));

        return trim($data['text']);
    }

    /**
     * Rédige de nouveaux blocs pour la page, au format de l'éditeur.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AiException
     */
    public function write(Site $site, string $instruction, string $pageText = '', ?string $pageLabel = null): array
    {
        $prompt = $this->context($site, $pageText, $pageLabel)
            ."\n\n## Demande\nRédige un passage à insérer dans cette page, selon la consigne ci-dessous. "
            .'Découpe-le en blocs : « heading » (intertitre court, sans point final), « paragraph » (2 à 4 phrases) ou « bullets » (liste dans « items », 3 à 6 éléments courts). '
            .'Laisse « items » vide pour les autres blocs et « text » vide pour une liste. Pas de titre principal : la page en a déjà un.'
            ."\n\nConsigne : ".trim($instruction);

        $schema = self::object(['blocks' => ['type' => 'array', 'items' => self::object([
            'type' => ['type' => 'string', 'enum' => ['heading', 'paragraph', 'bullets']],
            'text' => ['type' => 'string'],
            'items' => ['type' => 'array', 'items' => ['type' => 'string']],
        ])]]);

        $data = $this->generate($site, 'editor_write', $prompt, $schema, $pageText,
            ['instruction' => $instruction, 'page' => $pageLabel],
            function (array $data): array {
                $text = collect($data['blocks'])->map(fn (array $block): string => $block['text'].' '.implode(' ', $block['items']))->implode("\n");

                return [
                    ...($data['blocks'] === [] ? ['Aucun bloc rédigé.'] : []),
                    ...$this->textProblems($text),
                ];
            });

        return self::toBlocks($data['blocks']);
    }

    /**
     * Propose le title et la meta description d'une page à partir de son contenu.
     *
     * @return array{title: string, meta_description: string}
     *
     * @throws AiException
     */
    public function seo(Site $site, string $pageText, string $pageLabel, bool $isHome): array
    {
        $prompt = $this->context($site, $pageText, $pageLabel)
            ."\n\n## Demande\nPropose la balise title (30 à 65 caractères) et la meta description (120 à 155 caractères, phrase complète qui donne envie de cliquer) de cette page, "
            .($isHome ? 'qui est la page d\'accueil : le title contient l\'activité, la ville et le nom de l\'entreprise.' : 'en reflétant son sujet réel, avec le nom de l\'entreprise ou la ville dans le title.');

        $data = $this->generate($site, 'editor_seo', $prompt, self::object(['title' => ['type' => 'string'], 'meta_description' => ['type' => 'string']]), $pageText,
            ['page' => $pageLabel],
            function (array $data): array {
                $problems = $this->textProblems($data['title']."\n".$data['meta_description']);
                $title = mb_strlen(trim($data['title']));
                $description = mb_strlen(trim($data['meta_description']));

                if ($title < 20 || $title > 70) {
                    $problems[] = "Le title fait {$title} caractères (30 à 65 attendus).";
                }

                if ($description < 70 || $description > 170) {
                    $problems[] = "La meta description fait {$description} caractères (120 à 155 attendus).";
                }

                return $problems;
            });

        return ['title' => Str::limit(trim($data['title']), 70, ''), 'meta_description' => Str::limit(trim($data['meta_description']), 170, '')];
    }

    /**
     * @param  list<array{type: string, text: string, items: list<string>}>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function toBlocks(array $blocks): array
    {
        $text = fn (string $value): array => trim($value) === '' ? [] : [['type' => 'text', 'text' => trim($value)]];
        $nodes = [];

        foreach ($blocks as $block) {
            $node = match ($block['type']) {
                'heading' => trim($block['text']) === '' ? null : ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => $text(rtrim($block['text'], '.'))],
                'bullets' => ($items = array_values(array_filter(array_map(trim(...), $block['items'])))) === [] ? null : [
                    'type' => 'bulletList',
                    'content' => array_map(fn (string $item): array => ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => $text($item)]]], $items),
                ],
                default => null,
            };

            if ($block['type'] === 'paragraph') {
                foreach (preg_split('/\R{2,}/u', trim($block['text'])) ?: [] as $paragraph) {
                    if (trim($paragraph) !== '') {
                        $nodes[] = ['type' => 'paragraph', 'content' => $text($paragraph)];
                    }
                }
            } elseif ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $logInput
     * @param  callable(array<string, mixed>): list<string>  $check
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    private function generate(Site $site, string $schemaName, string $prompt, array $schema, string $knownText, array $logInput, callable $check): array
    {
        $facts = $this->validator->briefText($site->brief)."\n".$knownText;
        $problems = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = $this->ai->generate('editor', new AiRequest(
                system: File::get(base_path('prompts/'.config('ai.prompt_version').'/editor.md')),
                prompt: $problems === [] ? $prompt : $prompt."\n\nUne première proposition a été refusée pour les raisons suivantes. Corrige ces points :\n- ".implode("\n- ", $problems),
                schemaName: $schemaName,
                schema: $schema,
                maxTokens: 4000,
            ), $site, [...$logInput, 'attempt' => $attempt]);

            $allText = collect($response->data)->flatten()->filter(fn (mixed $value): bool => is_string($value))->implode("\n");
            $problems = [...$check($response->data), ...$this->validator->unsupportedClaims($allText, $facts)];

            if ($problems === []) {
                return $response->data;
            }
        }

        throw new AiException("La proposition de l'IA ne respecte pas les règles :\n- ".implode("\n- ", array_unique($problems)));
    }

    /**
     * @return list<string>
     */
    private function textProblems(string $text): array
    {
        if (trim($text) === '') {
            return ['Le texte est vide.'];
        }

        return preg_match('/<\/?[a-z][^>]*>|\*\*|^#+\s/imu', $text) ? ['Le texte doit être brut : pas de HTML ni de Markdown.'] : [];
    }

    private function context(Site $site, string $pageText, ?string $pageLabel = null): string
    {
        $brief = $site->brief;
        $facts = array_filter([
            'entreprise' => $brief['business_name'],
            'activite' => $brief['activity'],
            'description' => $brief['description'] ?? null,
            'services' => collect($brief['services'] ?? [])->map(fn (array $service): array => array_filter(['nom' => $service['name'] ?? null, 'precisions' => $service['description'] ?? null]))->values()->all(),
            'ville_principale' => $brief['city'],
            'communes_desservies' => $brief['service_area'] ?? [],
            'horaires' => $brief['opening_hours'] ?? [],
            'precision_horaires' => $brief['opening_hours_note'] ?? null,
            'ambiance' => SiteStyle::tryFrom($brief['style'] ?? '')?->getLabel(),
            'consignes_de_redaction' => $brief['notes'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== []);

        $context = "## Informations fournies par le client (seule source de faits autorisée)\n"
            .json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (trim($pageText) !== '') {
            $context .= "\n\n## Contenu actuel de la page".($pageLabel !== null ? " « {$pageLabel} »" : '')."\n".Str::limit(trim($pageText), 6000);
        } elseif ($pageLabel !== null) {
            $context .= "\n\n## Page\n« {$pageLabel} » (encore vide)";
        }

        return $context;
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
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
