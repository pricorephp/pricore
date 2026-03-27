<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Exceptions\AdvisorySyncException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PackagistAdvisoryClient
{
    protected PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl('https://packagist.org')
            ->timeout(60)
            ->connectTimeout(10)
            ->retry(3, function (int $attempt) {
                return $attempt * 5000;
            });
    }

    /**
     * Fetch all advisories (initial sync).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function fetchAll(): array
    {
        return $this->fetch(['updatedSince' => 0]);
    }

    /**
     * Fetch advisories updated since a given timestamp.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function fetchUpdatedSince(int $timestamp): array
    {
        return $this->fetch(['updatedSince' => $timestamp]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function fetch(array $params): array
    {
        $response = $this->http->get('/api/security-advisories/', $params);

        if (! $response->successful()) {
            throw new AdvisorySyncException(
                "Failed to fetch advisories from Packagist: HTTP {$response->status()}"
            );
        }

        return $response->json('advisories', []);
    }
}
