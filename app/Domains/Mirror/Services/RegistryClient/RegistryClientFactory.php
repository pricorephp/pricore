<?php

namespace App\Domains\Mirror\Services\RegistryClient;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Mirror\Contracts\Interfaces\RegistryClientInterface;
use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Models\Mirror;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RegistryClientFactory
{
    public static function make(Mirror $mirror): RegistryClientInterface
    {
        $httpClient = static::createHttpClient($mirror);

        $packagesUrl = Str::finish($mirror->url, '/').'packages.json';

        $response = $httpClient->get($packagesUrl);

        if (! $response->successful()) {
            throw new MirrorSyncException(
                "Failed to fetch packages.json from {$mirror->url}: HTTP {$response->status()}"
            );
        }

        $rootMetadata = $response->json();

        if (! is_array($rootMetadata)) {
            throw new MirrorSyncException(
                "Invalid packages.json response from {$mirror->url}: expected JSON object"
            );
        }

        return static::createClient($httpClient, $rootMetadata, $mirror);
    }

    public static function validateConnection(Mirror $mirror): bool
    {
        try {
            $httpClient = static::createHttpClient($mirror);

            $packagesUrl = Str::finish($mirror->url, '/').'packages.json';

            $response = $httpClient->get($packagesUrl);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function createHttpClient(Mirror $mirror): PendingRequest
    {
        $client = Http::timeout(30)
            ->connectTimeout(10)
            ->withUserAgent('Pricore Mirror Sync');

        $credentials = $mirror->auth_credentials;

        return match ($mirror->auth_type) {
            MirrorAuthType::Basic => $client->withBasicAuth(
                $credentials['username'] ?? '',
                $credentials['password'] ?? '',
            ),
            MirrorAuthType::Bearer => $client->withToken(
                $credentials['token'] ?? '',
            ),
            MirrorAuthType::None => $client,
        };
    }

    /**
     * @param  array<string, mixed>  $rootMetadata
     */
    protected static function createClient(
        PendingRequest $httpClient,
        array $rootMetadata,
        Mirror $mirror,
    ): RegistryClientInterface {
        // Inline format: packages key contains all package data directly
        $packages = $rootMetadata['packages'] ?? null;

        if (is_array($packages) && ! empty($packages)) {
            return new InlineRegistryClient($httpClient, $packages);
        }

        // Includes format: packages are in separate files referenced by "includes"
        $includes = $rootMetadata['includes'] ?? null;

        if (is_array($includes) && ! empty($includes)) {
            $packages = static::fetchIncludes($httpClient, $includes, $mirror->url);

            return new InlineRegistryClient($httpClient, $packages);
        }

        throw new MirrorSyncException(
            "Unsupported registry format at {$mirror->url}: no packages or includes found in packages.json"
        );
    }

    /**
     * Fetch package data from include files referenced in packages.json.
     *
     * @param  array<string, array<string, string>>  $includes
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected static function fetchIncludes(
        PendingRequest $httpClient,
        array $includes,
        string $baseUrl,
    ): array {
        $packages = [];
        $baseUrl = Str::finish($baseUrl, '/');

        foreach (array_keys($includes) as $includePath) {
            $includeUrl = $baseUrl.$includePath;

            $response = $httpClient->get($includeUrl);

            if (! $response->successful()) {
                throw new MirrorSyncException(
                    "Failed to fetch include file {$includePath}: HTTP {$response->status()}"
                );
            }

            $includeData = $response->json();

            if (is_array($includeData) && isset($includeData['packages']) && is_array($includeData['packages'])) {
                $packages = array_merge($packages, $includeData['packages']);
            }
        }

        return $packages;
    }
}
