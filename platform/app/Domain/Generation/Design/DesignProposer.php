<?php

namespace App\Domain\Generation\Design;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Domain\Sites\ColorPalette;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Domain\Sites\SiteTemplates;
use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Enums\SiteStyle;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Demande à l'IA trois directions de design : chacune part d'un thème (SiteTemplates) dont l'IA
 * ajuste la structure (navigation, bandeau, agencement des sections), le style et les couleurs,
 * uniquement parmi les choix fermés de Design.
 */
class DesignProposer
{
    private const PROPOSALS = 3;

    private const MAX_PHOTOS = 4;

    public function __construct(private AiManager $ai, private DraftSpecFactory $drafts) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws AiException
     */
    public function propose(Site $site, ?string $instructions = null): array
    {
        $current = $this->drafts->design($site);
        $photos = $this->photos($site);

        $response = $this->ai->generate(
            'design',
            new AiRequest(
                system: File::get(base_path('prompts/'.config('ai.prompt_version').'/design.md')),
                prompt: $this->prompt($site, $current, $photos->isNotEmpty(), $instructions),
                schemaName: 'design_proposals',
                schema: $this->schema(),
                images: $photos->map(fn (Media $media): array => [
                    'media_type' => 'image/jpeg',
                    'data' => base64_encode(Storage::disk(config('vitrines.media.disk'))->get($media->variants[0]['files']['jpg'])),
                ])->values()->all(),
                maxTokens: 6000,
            ),
            $site,
            ['instructions' => $instructions, 'photos' => $photos->pluck('id')->all()],
        );

        $proposals = array_slice($response->data['proposals'] ?? [], 0, self::PROPOSALS);

        if (count($proposals) < 2) {
            throw new AiException('L\'IA n\'a pas renvoyé assez de propositions de design.', true);
        }

        return array_map(function (array $proposal) use ($photos): array {
            $template = SiteTemplates::exists($proposal['template'] ?? null) ? $proposal['template'] : array_key_first(SiteTemplates::ALL);
            $design = Design::normalize($proposal, SiteTemplates::design($template));
            $design['primary'] = $this->readablePrimary($design['primary']);

            if ($photos->isEmpty()) {
                $design['hero_layout'] = 'plain';
            }

            return $design;
        }, $proposals);
    }

    /**
     * Une couleur principale trop pâle est assombrie jusqu'à rester lisible sur fond blanc.
     */
    private function readablePrimary(string $color): string
    {
        return ColorPalette::contrast($color, '#ffffff') < 3.0 ? ColorPalette::from($color)['primary_strong'] : $color;
    }

    /**
     * Photos les plus représentatives, dans leur plus petite variante.
     *
     * @return Collection<int, Media>
     */
    private function photos(Site $site): Collection
    {
        $priority = [MediaCategory::Hero, MediaCategory::Work, MediaCategory::Premises, MediaCategory::Team];

        return $site->media()
            ->where('status', MediaStatus::Ready)
            ->get()
            ->filter(fn (Media $media): bool => isset($media->variants[0]['files']['jpg']) && $media->category !== MediaCategory::Logo)
            ->sortBy(fn (Media $media): int => ($index = array_search($media->category, $priority, true)) === false ? 99 : $index)
            ->take(self::MAX_PHOTOS)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private function prompt(Site $site, array $current, bool $hasPhotos, ?string $instructions): string
    {
        $brief = $site->brief;

        $facts = array_filter([
            'entreprise' => $brief['business_name'],
            'activite' => $brief['activity'],
            'description' => $brief['description'] ?? null,
            'services' => collect($brief['services'] ?? [])->pluck('name')->filter()->values()->all(),
            'ville' => $brief['city'],
            'ambiance_souhaitee' => SiteStyle::tryFrom($brief['style'] ?? '')?->getLabel(),
            'couleur_principale_actuelle' => $current['primary'],
            'couleur_secondaire_actuelle' => $current['secondary'],
            'photos_jointes' => $hasPhotos ? 'oui' : 'non',
        ]);

        $templates = collect(SiteTemplates::ALL)->map(fn (array $template, string $key): array => [
            'nom' => $template['label'],
            'description' => $template['description'],
            'ideal_pour' => $template['ideal_for'],
            'reglages' => SiteTemplates::design($key),
        ])->all();

        $json = fn (array $data): string => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $prompt = "Entreprise :\n".$json($facts)."\n\nThèmes de départ (avec leurs réglages complets) :\n".$json($templates);

        if (filled($instructions)) {
            $prompt .= "\n\nConsignes de l'équipe :\n".trim($instructions);
        }

        return $prompt;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $properties = [
            'template' => ['type' => 'string', 'enum' => array_keys(SiteTemplates::ALL), 'description' => 'Thème de départ'],
            'name' => ['type' => 'string'],
            'rationale' => ['type' => 'string'],
            'primary' => ['type' => 'string', 'description' => 'Couleur #rrggbb'],
            'secondary' => ['type' => 'string', 'description' => 'Couleur #rrggbb'],
            'font_pair' => ['type' => 'string', 'enum' => array_keys(Design::FONT_PAIRS), 'description' => $this->describe(array_map(fn (array $pair): string => $pair['label'], Design::FONT_PAIRS))],
        ];

        foreach (Design::OPTIONS as $token => $choices) {
            $properties[$token] = ['type' => 'string', 'enum' => array_keys($choices), 'description' => Design::LABELS[$token].' : '.$this->describe($choices)];
        }

        $proposal = [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => ['proposals' => ['type' => 'array', 'items' => $proposal]],
            'required' => ['proposals'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, string>  $choices
     */
    private function describe(array $choices): string
    {
        return collect($choices)->map(fn (string $label, string $value): string => "{$value} = {$label}")->implode(' ; ');
    }
}
