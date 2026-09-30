<?php

namespace App\Domain\Generation;

use RuntimeException;
use Throwable;

/**
 * Échec d'un appel d'IA. « retryable » indique qu'un autre essai (ou un autre fournisseur) peut réussir.
 */
class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
