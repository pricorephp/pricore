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
    ) {}

    public function getAvailablePackages(): array
    {
        return array_keys($this->packages);
    }

    public function getPackageVersions(string $packageName): array
    {
        return $this->packages[$packageName] ?? [];
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
