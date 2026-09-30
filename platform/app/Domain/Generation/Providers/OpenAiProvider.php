<?php

namespace App\Domain\Generation\Providers;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\AiResponse;
use App\Domain\Generation\Contracts\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Génération structurée via l'API OpenAI (endpoint Responses, sortie json_schema stricte).
 */
class OpenAiProvider implements AiProvider
{
    /**
     * @param  array{api_key: ?string, base_url: string, default_model: ?string, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key']) && filled($this->config['default_model']);
    }

    public function generateStructured(AiRequest $request, ?string $model = null): AiResponse
    {
        $model ??= $this->config['default_model'];

        $content = [['type' => 'input_text', 'text' => $request->prompt]];

        foreach ($request->images as $image) {
            $content[] = ['type' => 'input_image', 'image_url' => 'data:'.$image['media_type'].';base64,'.$image['data']];
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->withToken($this->config['api_key'])
                ->timeout($this->config['timeout'])
                ->acceptJson()
                ->post('/responses', [
                    'model' => $model,
                    'instructions' => $request->system,
                    'input' => [['role' => 'user', 'content' => $content]],
                    'max_output_tokens' => $request->maxTokens,
                    'text' => ['format' => [
                        'type' => 'json_schema',
                        'name' => $request->schemaName,
                        'schema' => $request->schema,
                        'strict' => true,
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new AiException('Impossible de joindre l\'API OpenAI.', true, $exception);
        }

        if ($response->status() === 401) {
            throw new AiException('Clé API OpenAI refusée.');
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new AiException('API OpenAI momentanément indisponible.', true);
        }

        if ($response->failed()) {
            throw new AiException('Requête refusée par l\'API OpenAI : '.($response->json('error.message') ?? $response->status()));
        }

        if ($response->json('status') === 'incomplete') {
            throw new AiException('Réponse tronquée ('.($response->json('incomplete_details.reason') ?? 'raison inconnue').').', true);
        }

        $text = '';

        foreach ($response->json('output', []) as $item) {
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new AiException('Le modèle a refusé la demande : '.($part['refusal'] ?? ''));
                }

                if (($part['type'] ?? null) === 'output_text') {
                    $text .= $part['text'];
                }
            }
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new AiException('Réponse illisible (JSON invalide).', true);
        }

        return new AiResponse(
            data: $data,
            provider: $this->name(),
            model: (string) $response->json('model', $model),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
        );
    }
}
