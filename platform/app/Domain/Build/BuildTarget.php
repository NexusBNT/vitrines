<?php

namespace App\Domain\Build;

/**
 * Destination d'un build : prévisualisation (non indexable) ou production.
 */
final readonly class BuildTarget
{
    public function __construct(
        public bool $production,
        public string $origin,
        public string $basePath = '/',
    ) {}

    public static function preview(string $origin, string $basePath = '/'): self
    {
        return new self(false, rtrim($origin, '/'), '/'.trim($basePath, '/').($basePath === '/' ? '' : '/'));
    }

    public static function production(string $origin): self
    {
        return new self(true, rtrim($origin, '/'));
    }

    public function indexable(): bool
    {
        return $this->production;
    }
}
