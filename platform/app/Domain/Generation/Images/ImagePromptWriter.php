<?php

namespace App\Domain\Generation\Images;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * Fait rédiger par l'IA la consigne et le texte alternatif de chaque illustration à générer.
 */
class ImagePromptWriter
{
    public function __construct(private AiManager $ai) {}

    /**
     * @param  array<string, string>  $slots  Emplacement => sujet (ex. « service-0 » => « Dépannage plomberie »)
     * @param  array<string, mixed>  $design
     * @return array<string, array{prompt: string, alt: string}>
     *
     * @throws AiException
     */
    public function write(Site $site, array $slots, array $design): array
    {
        $brief = $site->brief;

        $facts = json_encode([
            'entreprise' => $brief['business_name'],
            'activite' => $brief['activity'],
            'description' => $brief['description'] ?? null,
            'ville' => $brief['city'],
            'couleurs_du_site' => array_values(array_filter([$design['primary'] ?? null, $design['secondary'] ?? null])),
            'emplacements' => $slots,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->ai->generate(
            'image_prompts',
            new AiRequest(
                system: File::get(base_path('prompts/'.config('ai.prompt_version').'/image_prompts.md')),
                prompt: "Rédige une consigne pour chacun de ces emplacements :\n".$facts,
                schemaName: 'image_prompts',
                schema: [
                    'type' => 'object',
                    'properties' => ['images' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slot' => ['type' => 'string', 'enum' => array_keys($slots)],
                            'prompt' => ['type' => 'string'],
                            'alt' => ['type' => 'string'],
                        ],
                        'required' => ['slot', 'prompt', 'alt'],
                        'additionalProperties' => false,
                    ]]],
                    'required' => ['images'],
                    'additionalProperties' => false,
                ],
                maxTokens: 6000,
            ),
            $site,
            ['slots' => array_keys($slots)],
        );

        $prompts = [];

        foreach ($response->data['images'] as $image) {
            if (isset($slots[$image['slot']]) && filled($image['prompt'])) {
                $prompts[$image['slot']] = [
                    'prompt' => trim($image['prompt']).' Photorealistic editorial photography. No identifiable people or faces, no text, no logos, no brand names.',
                    'alt' => mb_substr(trim($image['alt']), 0, 250),
                ];
            }
        }

        return $prompts;
    }
}
