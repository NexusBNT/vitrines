<?php

namespace App\Domain\Generation\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\AiResponse;
use App\Domain\Generation\Contracts\AiProvider;

/**
 * Génération structurée via l'API Claude (SDK PHP officiel).
 *
 * Le repli serveur (« fallbacks: default ») relance automatiquement la requête sur un
 * autre modèle Claude si le modèle principal refuse ; un refus final est remonté en erreur.
 */
class ClaudeProvider implements AiProvider
{
    /**
     * @param  array{api_key: ?string, default_model: string, effort: string, timeout: int}  $config
     */
    public function __construct(private array $config, private ?Client $client = null) {}

    public function name(): string
    {
        return 'claude';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key']);
    }

    public function generateStructured(AiRequest $request, ?string $model = null): AiResponse
    {
        $model ??= $this->config['default_model'];

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: $request->maxTokens,
                messages: [['role' => 'user', 'content' => $this->content($request)]],
                model: $model,
                system: $request->system,
                outputConfig: [
                    'effort' => $this->config['effort'],
                    'format' => ['type' => 'json_schema', 'schema' => $request->schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $exception) {
            throw new AiException('Clé API Anthropic refusée.', false, $exception);
        } catch (BadRequestException $exception) {
            throw new AiException('Requête refusée par l\'API Anthropic : '.$exception->getMessage(), false, $exception);
        } catch (RateLimitException|InternalServerException $exception) {
            throw new AiException('API Anthropic momentanément indisponible.', true, $exception);
        } catch (APIStatusException $exception) {
            throw new AiException('Erreur de l\'API Anthropic : '.$exception->getMessage(), true, $exception);
        } catch (APIConnectionException $exception) {
            throw new AiException('Impossible de joindre l\'API Anthropic.', true, $exception);
        }

        if ($message->stopReason === 'refusal') {
            throw new AiException('Le modèle a refusé la demande'.($message->stopDetails?->explanation ? ' : '.$message->stopDetails->explanation : '.'));
        }

        if ($message->stopReason === 'max_tokens') {
            throw new AiException('Réponse tronquée (limite de longueur atteinte).', true);
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new AiException('Réponse illisible (JSON invalide).', true);
        }

        return new AiResponse(
            data: $data,
            provider: $this->name(),
            model: $message->model,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function content(AiRequest $request): array
    {
        $content = [];

        foreach ($request->images as $image) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $image['media_type'], 'data' => $image['data']]];
        }

        $content[] = ['type' => 'text', 'text' => $request->prompt];

        return $content;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: $this->config['api_key'],
            requestOptions: ['timeout' => (float) $this->config['timeout'], 'maxRetries' => 2],
        );
    }
}
