<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractGitProvider implements GitProviderInterface
{
    protected PendingRequest $http;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        protected string $repositoryIdentifier,
        protected array $credentials
    ) {
        $this->http = $this->configureHttpClient();
    }

    /**
     * Configure the HTTP client with authentication and base URL.
     */
    abstract protected function configureHttpClient(): PendingRequest;

    public function getRepositoryIdentifier(): string
    {
        return $this->repositoryIdentifier;
    }

    protected function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    protected function hasCredential(string $key): bool
    {
        return isset($this->credentials[$key]);
    }
}
