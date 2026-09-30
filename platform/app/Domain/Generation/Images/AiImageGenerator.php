<?php

namespace App\Domain\Generation\Images;

use App\Domain\Generation\AiException;
use App\Domain\Generation\Providers\OpenAiImageProvider;
use App\Enums\GenerationStatus;
use App\Enums\MediaCategory;
use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use App\Jobs\ProcessMedia;
use App\Models\AiGeneration;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Génère les illustrations manquantes d'un site (bandeau d'accueil, présentation, services).
 *
 * Les photos du client restent prioritaires : une illustration n'est créée que pour un emplacement
 * qu'aucune photo ne couvre. Les illustrations ne sont jamais classées comme réalisations.
 */
class AiImageGenerator
{
    public function __construct(
        private ImagePromptWriter $writer,
        private OpenAiImageProvider $images,
    ) {}

    public function isAvailable(): bool
    {
        return $this->images->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array{created: int, warnings: list<string>}
     *
     * @throws AiException
     */
    public function generate(Site $site, array $design, bool $replace = false): array
    {
        if (! $this->isAvailable()) {
            throw new AiException('Aucun générateur d\'images n\'est configuré : ajoutez OPENAI_API_KEY dans le fichier .env.');
        }

        if ($replace) {
            $site->media()->where('source', MediaSource::Ai)->get()->each->delete();
        }

        $slots = $this->missingSlots($site);

        if ($slots === []) {
            return ['created' => 0, 'warnings' => []];
        }

        $prompts = $this->writer->write($site, $slots, $design);
        $startedAt = hrtime(true);
        $results = $this->images->generateMany(array_map(fn (array $image): string => $image['prompt'], $prompts));

        foreach ($results as $slot => $result) {
            if ($result instanceof AiException && $result->retryable) {
                try {
                    $results[$slot] = $this->images->generate($prompts[$slot]['prompt']);
                } catch (AiException $exception) {
                    $results[$slot] = $exception;
                }
            }
        }

        $durationMs = $this->elapsedMs($startedAt);
        $created = 0;
        $warnings = [];

        foreach ($results as $slot => $result) {
            $generation = AiGeneration::create([
                'site_id' => $site->getKey(),
                'user_id' => auth()->id(),
                'task' => 'image',
                'provider' => 'openai',
                'model' => $this->images->model(),
                'prompt_version' => config('ai.prompt_version'),
                'input' => ['slot' => $slot, 'prompt' => $prompts[$slot]['prompt']],
                'duration_ms' => $durationMs,
            ]);

            if ($result instanceof AiException) {
                $generation->update(['status' => GenerationStatus::Failed, 'error' => Str::limit($result->getMessage(), 2000)]);
                $warnings[] = "Illustration « {$slots[$slot]} » non générée : {$result->getMessage()}";

                continue;
            }

            $generation->update([
                'status' => GenerationStatus::Succeeded,
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);

            $this->storeIllustration($site, $slot, $prompts[$slot]['prompt'], $prompts[$slot]['alt'], $result['jpeg']);
            $created++;
        }

        return ['created' => $created, 'warnings' => $warnings];
    }

    /**
     * Emplacements sans photo du client ni illustration existante.
     *
     * @return array<string, string>
     */
    public function missingSlots(Site $site): array
    {
        $media = $site->media()->where('status', MediaStatus::Ready)->get();
        $uploads = $media->where('source', MediaSource::Upload);
        $existingSlots = $media->where('source', MediaSource::Ai)->pluck('ai_slot')->all();

        $slots = [];

        if ($uploads->whereIn('category', [MediaCategory::Hero, MediaCategory::Work, MediaCategory::Premises])->isEmpty()) {
            $slots['hero'] = 'Image d\'accueil : '.$site->brief['activity'];
        }

        if ($uploads->whereIn('category', [MediaCategory::Team, MediaCategory::Premises])->isEmpty()) {
            $slots['about'] = 'Présentation de l\'entreprise';
        }

        $services = collect($site->brief['services'] ?? [])->pluck('name')->filter()->values()->take(config('ai.max_service_images'));

        foreach ($services as $index => $name) {
            $slots["service-{$index}"] = 'Service : '.$name;
        }

        return array_diff_key($slots, array_flip($existingSlots));
    }

    private function storeIllustration(Site $site, string $slot, string $prompt, string $alt, string $jpeg): Media
    {
        $disk = Storage::disk(config('vitrines.media.disk'));
        $sha256 = hash('sha256', $jpeg);
        $path = sprintf('originals/%s/%s.jpg', $site->directoryName(), $sha256);
        $disk->put($path, $jpeg);

        $media = $site->media()->create([
            'source' => MediaSource::Ai,
            'ai_slot' => $slot,
            'ai_prompt' => $prompt,
            'sha256' => $sha256,
            'original_path' => $path,
            'original_name' => "illustration-{$slot}.jpg",
            'mime' => 'image/jpeg',
            'bytes' => strlen($jpeg),
            'alt' => $alt,
            'category' => $slot === 'hero' ? MediaCategory::Hero : MediaCategory::Other,
            'sort_order' => (int) $site->media()->max('sort_order') + 1,
        ]);

        ProcessMedia::dispatchSync($media);

        return $media->refresh();
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
