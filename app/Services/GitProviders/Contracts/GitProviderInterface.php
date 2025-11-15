<?php

namespace App\Services\GitProviders\Contracts;

interface GitProviderInterface
{
    /**
     * Get all tags from the repository.
     *
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array;

    /**
     * Get all branches from the repository.
     *
     * @return array<int, array{name: string, commit: string}>
     */
    public function getBranches(): array;

    /**
     * Get file content from a specific reference (branch, tag, or commit).
     */
    public function getFileContent(string $ref, string $path): ?string;

    /**
     * Validate that the credentials are valid and can access the repository.
     */
    public function validateCredentials(): bool;

    /**
     * Get the repository identifier (e.g., owner/repo).
     */
    public function getRepositoryIdentifier(): string;

    /**
     * Get the Git repository URL for cloning.
     */
    public function getRepositoryUrl(): string;
}
