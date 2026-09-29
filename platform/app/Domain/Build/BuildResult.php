<?php

namespace App\Domain\Build;

final readonly class BuildResult
{
    /**
     * @param  list<array{level: string, page: string, message: string}>  $issues
     */
    public function __construct(
        public string $buildId,
        public string $path,
        public array $issues,
        public int $pageCount,
    ) {}

    public function hasErrors(): bool
    {
        return collect($this->issues)->contains('level', 'error');
    }

    /**
     * @return list<array{level: string, page: string, message: string}>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->issues, fn (array $issue): bool => $issue['level'] === 'error'));
    }

    /**
     * @return list<array{level: string, page: string, message: string}>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, fn (array $issue): bool => $issue['level'] === 'warning'));
    }
}
