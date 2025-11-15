<?php

namespace App\Services\GitProviders;

use App\Services\GitProviders\Contracts\GitProviderInterface;
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

    /**
     * Get the repository identifier.
     */
    public function getRepositoryIdentifier(): string
    {
        return $this->repositoryIdentifier;
    }

    /**
     * Get credential value by key.
     */
    protected function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Check if a credential exists.
     */
    protected function hasCredential(string $key): bool
    {
        return isset($this->credentials[$key]);
    }
}
