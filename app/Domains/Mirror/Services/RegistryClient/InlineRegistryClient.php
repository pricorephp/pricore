<?php

namespace App\Domains\Mirror\Services\RegistryClient;

use App\Domains\Mirror\Contracts\Interfaces\RegistryClientInterface;
use Illuminate\Http\Client\PendingRequest;

class InlineRegistryClient implements RegistryClientInterface
{
    /**
     * @param  array<string, array<string, array<string, mixed>>>  $packages
     */
    public function __construct(
        protected PendingRequest $httpClient,
        protected array $packages,
    ) {
        $this->packages = $this->normalizePackages($packages);
    }

    public function getAvailablePackages(): array
    {
        return array_keys($this->packages);
    }

    public function getPackageVersions(string $packageName): array
    {
        return $this->packages[$packageName] ?? [];
    }

    /**
     * Normalize packages so versions are keyed by version string.
     *
     * Some registries return versions as a JSON array instead of an object,
     * resulting in numeric keys (0, 1, 2) instead of version strings.
     *
     * @param  array<string, array<int|string, array<string, mixed>>>  $packages
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function normalizePackages(array $packages): array
    {
        $normalized = [];

        foreach ($packages as $packageName => $versions) {
            $normalizedVersions = [];

            foreach ($versions as $key => $composerJson) {
                $version = (string) ($composerJson['version'] ?? $key);
                $normalizedVersions[$version] = $composerJson;
            }

            $normalized[$packageName] = $normalizedVersions;
        }

        return $normalized;
    }

    public function validateConnection(): bool
    {
        return true;
    }

    public function downloadDist(string $url, string $outputPath): bool
    {
        $response = $this->httpClient->withOptions([
            'sink' => $outputPath,
        ])->get($url);

        return $response->successful();
    }
}
