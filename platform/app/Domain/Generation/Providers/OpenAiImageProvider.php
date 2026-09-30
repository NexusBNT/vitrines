<?php

namespace App\Domain\Generation\Providers;

use App\Domain\Generation\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Génération d'images via l'API OpenAI (endpoint images/generations).
 */
class OpenAiImageProvider
{
    /**
     * @param  array{api_key: ?string, base_url: string, image_model: string, image_quality: string, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return filled($this->config['api_key']) && filled($this->config['image_model']);
    }

    public function model(): string
    {
        return $this->config['image_model'];
    }

    /**
     * @return array{jpeg: string, input_tokens: int, output_tokens: int}
     *
     * @throws AiException
     */
    public function generate(string $prompt, string $size = '1536x1024'): array
    {
        try {
            $response = $this->request()->post('/images/generations', $this->payload($prompt, $size));
        } catch (ConnectionException $exception) {
            throw new AiException('Impossible de joindre l\'API OpenAI (images).', true, $exception);
        }

        return $this->parse($response);
    }

    /**
     * Génère plusieurs images en parallèle. Chaque résultat est une image ou l'exception de son échec.
     *
     * @param  array<string, string>  $prompts  Clé => consigne
     * @return array<string, array{jpeg: string, input_tokens: int, output_tokens: int}|AiException>
     */
    public function generateMany(array $prompts, string $size = '1536x1024'): array
    {
        $responses = Http::pool(fn (Pool $pool): array => collect($prompts)
            ->map(fn (string $prompt, string $key) => $this->request($pool->as($key))->post('/images/generations', $this->payload($prompt, $size)))
            ->values()
            ->all());

        $results = [];

        foreach (array_keys($prompts) as $key) {
            $response = $responses[$key] ?? null;

            try {
                $results[$key] = $response instanceof Response
                    ? $this->parse($response)
                    : throw new AiException('Impossible de joindre l\'API OpenAI (images).', true);
            } catch (AiException $exception) {
                $results[$key] = $exception;
            }
        }

        return $results;
    }

    private function request(?PendingRequest $request = null): PendingRequest
    {
        return ($request ?? Http::createPendingRequest())
            ->baseUrl($this->config['base_url'])
            ->withToken($this->config['api_key'])
            ->timeout($this->config['timeout'])
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $prompt, string $size): array
    {
        return [
            'model' => $this->config['image_model'],
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $this->config['image_quality'],
            'output_format' => 'jpeg',
            'n' => 1,
        ];
    }

    /**
     * @return array{jpeg: string, input_tokens: int, output_tokens: int}
     *
     * @throws AiException
     */
    private function parse(Response $response): array
    {
        if ($response->status() === 429 || $response->serverError()) {
            throw new AiException('API OpenAI (images) momentanément indisponible.', true);
        }

        if ($response->failed()) {
            throw new AiException('Image refusée par l\'API OpenAI : '.($response->json('error.message') ?? $response->status()));
        }

        $jpeg = base64_decode((string) $response->json('data.0.b64_json'), true);

        if ($jpeg === false || $jpeg === '') {
            throw new AiException('Image vide renvoyée par l\'API OpenAI.', true);
        }

        return [
            'jpeg' => $jpeg,
            'input_tokens' => (int) $response->json('usage.input_tokens', 0),
            'output_tokens' => (int) $response->json('usage.output_tokens', 0),
        ];
    }
}
