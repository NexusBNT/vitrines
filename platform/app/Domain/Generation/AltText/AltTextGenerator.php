<?php

namespace App\Domain\Generation\AltText;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Enums\MediaCategory;
use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Propose un texte alternatif, un usage et une légende pour une photo, à partir de l'image elle-même.
 * Les valeurs déjà saisies par l'équipe ne sont jamais remplacées.
 */
class AltTextGenerator
{
    public function __construct(private AiManager $ai) {}

    /**
     * @throws AiException
     */
    public function describe(Media $media): Media
    {
        $site = $media->loadMissing('site')->site;
        $variant = collect($media->variants)->last(fn (array $variant): bool => $variant['width'] <= 800) ?? $media->variants[0];
        $image = Storage::disk(config('vitrines.media.disk'))->get($variant['files']['jpg']);

        $response = $this->ai->generate(
            'alt_text',
            new AiRequest(
                system: File::get(base_path('prompts/'.config('ai.prompt_version').'/alt_text.md')),
                prompt: sprintf('Entreprise : %s, %s à %s.', $site->brief['business_name'], $site->brief['activity'], $site->brief['city']),
                schemaName: 'photo_description',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'alt' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'enum' => array_column(MediaCategory::cases(), 'value')],
                        'caption' => ['type' => 'string'],
                    ],
                    'required' => ['alt', 'category', 'caption'],
                    'additionalProperties' => false,
                ],
                images: [['media_type' => 'image/jpeg', 'data' => base64_encode($image)]],
                maxTokens: 2000,
            ),
            $site,
            ['media_id' => $media->id],
        );

        $media->update([
            'alt' => filled($media->alt) ? $media->alt : Str::limit(trim($response->data['alt']), 250, ''),
            'category' => $media->category ?? MediaCategory::tryFrom($response->data['category']),
            'caption' => filled($media->caption) ? $media->caption : (Str::limit(trim($response->data['caption']), 120, '') ?: null),
        ]);

        return $media;
    }
}
