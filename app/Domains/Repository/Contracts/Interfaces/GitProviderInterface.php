<?php

namespace App\Domains\Repository\Contracts\Interfaces;

use Carbon\CarbonImmutable;

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

    public function getCommitDate(string $ref): ?CarbonImmutable;

    public function validateCredentials(): bool;

    public function getRepositoryIdentifier(): string;

    public function getRepositoryUrl(): string;

    /**
     * @return array{id: int|string}
     */
    public function createWebhook(string $url, string $secret): array;

    public function deleteWebhook(int|string $hookId): void;

    public function downloadArchive(string $ref, string $outputPath): bool;
}
