<?php

namespace App\Domain\Generation;

/**
 * Demande de génération structurée, indépendante du fournisseur.
 */
final readonly class AiRequest
{
    /**
     * @param  array<string, mixed>  $schema  JSON Schema de la réponse attendue
     * @param  list<array{media_type: string, data: string}>  $images  Images encodées en base64
     */
    public function __construct(
        public string $system,
        public string $prompt,
        public string $schemaName,
        public array $schema,
        public array $images = [],
        public int $maxTokens = 16000,
    ) {}
}
