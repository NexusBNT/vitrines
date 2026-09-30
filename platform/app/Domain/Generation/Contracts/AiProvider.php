<?php

namespace App\Domain\Generation\Contracts;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\AiResponse;

interface AiProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @throws AiException
     */
    public function generateStructured(AiRequest $request, ?string $model = null): AiResponse;
}
