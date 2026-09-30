<?php

namespace App\Domain\Generation;

use App\Domain\Generation\Contracts\AiProvider;
use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Point d'entrée unique vers l'IA : choisit le fournisseur de chaque tâche,
 * bascule sur le fournisseur de repli en cas de panne passagère et journalise chaque appel.
 */
class AiManager
{
    /**
     * @param  array<string, AiProvider>  $providers
     */
    public function __construct(private array $providers) {}

    public function isAvailable(string $task): bool
    {
        return $this->candidates($task) !== [];
    }

    /**
     * @param  array<string, mixed>  $logInput  Données d'entrée conservées dans le journal
     *
     * @throws AiException
     */
    public function generate(string $task, AiRequest $request, ?Site $site = null, array $logInput = []): AiResponse
    {
        $candidates = $this->candidates($task);

        if ($candidates === []) {
            throw new AiException('Aucun fournisseur d\'IA n\'est configuré pour cette tâche : ajoutez une clé API dans le fichier .env.');
        }

        $lastException = null;

        foreach ($candidates as [$provider, $model]) {
            $generation = AiGeneration::create([
                'site_id' => $site?->getKey(),
                'user_id' => auth()->id(),
                'task' => $task,
                'provider' => $provider->name(),
                'model' => $model,
                'prompt_version' => config('ai.prompt_version'),
                'input' => $logInput,
            ]);

            $startedAt = hrtime(true);

            try {
                $response = $provider->generateStructured($request, $model);
            } catch (AiException $exception) {
                $generation->update([
                    'status' => GenerationStatus::Failed,
                    'error' => Str::limit($exception->getMessage(), 2000),
                    'duration_ms' => $this->elapsedMs($startedAt),
                ]);

                $lastException = $exception;

                if (! $exception->retryable) {
                    throw $exception;
                }

                continue;
            }

            $generation->update([
                'status' => GenerationStatus::Succeeded,
                'model' => $response->model,
                'output' => $response->data,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return $response;
        }

        throw $lastException;
    }

    /**
     * Fournisseurs à essayer dans l'ordre : celui de la tâche, puis le repli.
     *
     * @return list<array{AiProvider, ?string}>
     */
    private function candidates(string $task): array
    {
        $taskConfig = config("ai.tasks.{$task}") ?? throw new AiException("Tâche d'IA inconnue : {$task}");
        $candidates = [];

        $primary = $this->providers[$taskConfig['provider']] ?? null;

        if ($primary?->isConfigured()) {
            $candidates[] = [$primary, filled($taskConfig['model']) ? $taskConfig['model'] : null];
        }

        $fallback = $this->providers[config('ai.fallback_provider')] ?? null;

        if ($fallback !== null && $fallback !== $primary && $fallback->isConfigured()) {
            $candidates[] = [$fallback, null];
        }

        return $candidates;
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
