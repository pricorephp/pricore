<?php

namespace App\Domains\Repository\Contracts\Interfaces;

interface GitProviderInterface
{
    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array;

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getBranches(): array;

    public function getFileContent(string $ref, string $path): ?string;

    public function validateCredentials(): bool;

    public function getRepositoryIdentifier(): string;

    public function getRepositoryUrl(): string;
}
