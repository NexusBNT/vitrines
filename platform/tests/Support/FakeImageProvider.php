<?php

namespace Tests\Support;

use App\Domain\Generation\AiException;
use App\Domain\Generation\Providers\OpenAiImageProvider;

/**
 * Générateur d'images simulé : produit de vrais JPEG (pour le pipeline d'images) ou échoue à la demande.
 */
class FakeImageProvider extends OpenAiImageProvider
{
    /** @var list<string> */
    public array $prompts = [];

    /**
     * @param  list<int>  $failOnCalls  Numéros d'appel (à partir de 1) qui doivent échouer
     */
    public function __construct(private array $failOnCalls = [], private bool $configured = true) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function model(): string
    {
        return 'fake-image';
    }

    public function generate(string $prompt, string $size = '1536x1024'): array
    {
        $this->prompts[] = $prompt;

        if (in_array(count($this->prompts), $this->failOnCalls, true)) {
            throw new AiException('Image refusée par le filtre de sécurité.');
        }

        $image = imagecreatetruecolor(900, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 20 * count($this->prompts), 120, 90));
        ob_start();
        imagejpeg($image);

        return ['jpeg' => ob_get_clean(), 'input_tokens' => 40, 'output_tokens' => 200];
    }

    public function generateMany(array $prompts, string $size = '1536x1024'): array
    {
        $results = [];

        foreach ($prompts as $key => $prompt) {
            try {
                $results[$key] = $this->generate($prompt, $size);
            } catch (AiException $exception) {
                $results[$key] = $exception;
            }
        }

        return $results;
    }
}
