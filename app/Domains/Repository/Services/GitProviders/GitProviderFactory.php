<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Models\OrganizationGitCredential;
use App\Models\Repository;

class GitProviderFactory
{
    public static function make(Repository $repository): GitProviderInterface
    {
        $credentials = static::getCredentials($repository);

        return match ($repository->provider) {
            GitProvider::GitHub => new GitHubProvider($repository->repo_identifier, $credentials),
            GitProvider::GitLab, GitProvider::Bitbucket, GitProvider::Git => throw new GitProviderException(
                "Provider '{$repository->provider->label()}' is not yet implemented"
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getCredentials(Repository $repository): array
    {
        $credential = OrganizationGitCredential::query()
            ->where('organization_uuid', $repository->organization_uuid)
            ->where('provider', $repository->provider)
            ->first();

        if (! $credential) {
            throw new GitProviderException(
                "No credentials found for provider '{$repository->provider->label()}' in organization"
            );
        }

        return $credential->credentials;
    }
}
