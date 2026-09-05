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

    /**
     * @return array{id: int|string}
     */
    public function createWebhook(string $url, string $secret): array;

    public function deleteWebhook(int|string $hookId): void;

    /**
     * Archive the repository at $ref, or only the subdirectory $path within it.
     * A subdirectory archive is re-rooted so the package sits at the top level.
     */
    public function downloadArchive(string $ref, string $outputPath, ?string $path = null): bool;

    /**
     * Immediate children of the directory $path at $ref. A missing directory yields an empty list.
     *
     * @return array<int, array{name: string, type: 'dir'|'file'}>
     */
    public function listDirectory(string $ref, string $path): array;
}
