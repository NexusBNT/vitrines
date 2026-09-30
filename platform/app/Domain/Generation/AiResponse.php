<?php

namespace App\Domain\Generation;

final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>  $data  Réponse décodée, conforme au schéma demandé
     */
    public function __construct(
        public array $data,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
