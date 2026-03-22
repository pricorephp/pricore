<?php

namespace App\Domains\Mirror\Contracts\Interfaces;

interface RegistryClientInterface
{
    /**
     * Get the list of available package names.
     *
     * @return array<int, string>
     */
    public function getAvailablePackages(): array;

    /**
     * Get all version metadata for a given package.
     *
     * @return array<string, array<string, mixed>> version => composer.json data
     */
    public function getPackageVersions(string $packageName): array;

    /**
     * Validate that the registry is reachable and credentials work.
     */
    public function validateConnection(): bool;

    /**
     * Download a dist archive from the upstream registry.
     */
    public function downloadDist(string $url, string $outputPath): bool;
}
