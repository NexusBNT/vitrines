<?php

namespace Tests\Support;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\AiResponse;
use App\Domain\Generation\Contracts\AiProvider;

/**
 * Fournisseur d'IA simulé : renvoie, dans l'ordre, les réponses (ou exceptions) préparées.
 */
class FakeAiProvider implements AiProvider
{
    /** @var list<AiRequest> */
    public array $requests = [];

    /**
     * @param  list<array<string, mixed>|AiException>  $responses
     */
    public function __construct(private array $responses, private string $name = 'claude', private bool $configured = true) {}

    public function name(): string
    {
        return $this->name;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function generateStructured(AiRequest $request, ?string $model = null): AiResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->responses) ?? throw new \LogicException('Aucune réponse simulée restante.');

        if ($next instanceof AiException) {
            throw $next;
        }

        return new AiResponse($next, $this->name, $model ?? 'fake-model', 1200, 3400);
    }
}
