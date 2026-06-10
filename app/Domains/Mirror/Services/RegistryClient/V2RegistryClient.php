<?php

namespace App\Domains\Mirror\Services\RegistryClient;

use App\Domains\Mirror\Contracts\Interfaces\RegistryClientInterface;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class V2RegistryClient implements RegistryClientInterface
{
    /**
     * @param  array<int, string>  $availablePackages
     */
    public function __construct(
        protected PendingRequest $httpClient,
        protected string $baseUrl,
        protected string $metadataUrlTemplate,
        protected array $availablePackages,
    ) {}

    public function getAvailablePackages(): array
    {
        return $this->availablePackages;
    }

    public function getPackageVersions(string $packageName): array
    {
        $versions = $this->fetchVersions($this->metadataUrl($packageName));

        // Dev versions live in a separate "~dev" metadata file. A missing file
        // is expected for packages without dev branches, so 404s are ignored.
        $devVersions = $this->fetchVersions($this->metadataUrl($packageName.'~dev'));

        return array_merge($versions, $devVersions);
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

    /**
     * Fetch and expand the minified version metadata at the given URL.
     *
     * @return array<string, array<string, mixed>> version => composer.json data
     */
    protected function fetchVersions(string $url): array
    {
        $response = $this->httpClient->get($url);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['packages']) || ! is_array($data['packages'])) {
            return [];
        }

        $versions = [];

        foreach ($data['packages'] as $minifiedVersions) {
            if (! is_array($minifiedVersions)) {
                continue;
            }

            foreach (MetadataMinifier::expand($minifiedVersions) as $composerJson) {
                $version = (string) ($composerJson['version'] ?? '');

                if ($version === '') {
                    continue;
                }

                $versions[$version] = $composerJson;
            }
        }

        return $versions;
    }

    /**
     * Build the metadata URL for a package by resolving the metadata-url
     * template against the registry base URL.
     */
    protected function metadataUrl(string $packageName): string
    {
        $path = str_replace('%package%', $packageName, $this->metadataUrlTemplate);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Str::finish($this->baseUrl, '/').ltrim($path, '/');
    }
}
