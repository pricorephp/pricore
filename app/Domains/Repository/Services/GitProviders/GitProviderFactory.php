<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Models\OrganizationSshKey;
use App\Models\Repository;
use App\Models\UserGitCredential;

class GitProviderFactory
{
    public static function make(Repository $repository): GitProviderInterface
    {
        $credentials = static::getCredentials($repository);

        return match ($repository->provider) {
            GitProvider::GitHub => new GitHubProvider($repository->repo_identifier, $credentials),
            GitProvider::Git => new GenericGitProvider($repository->repo_identifier, $credentials),
            GitProvider::GitLab => new GitLabProvider($repository->repo_identifier, $credentials),
            GitProvider::Bitbucket => throw new GitProviderException(
                "Provider '{$repository->provider->label()}' is not yet implemented"
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getCredentials(Repository $repository): array
    {
        if ($repository->provider === GitProvider::Git) {
            return static::getSshCredentials($repository);
        }

        $credential = UserGitCredential::query()
            ->where('user_uuid', $repository->credential_user_uuid)
            ->where('provider', $repository->provider)
            ->first();

        if (! $credential) {
            return [];
        }

        return $credential->credentials ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getSshCredentials(Repository $repository): array
    {
        if (! $repository->ssh_key_uuid) {
            return [];
        }

        $organizationSshKey = OrganizationSshKey::find($repository->ssh_key_uuid);

        if (! $organizationSshKey) {
            return [];
        }

        return ['ssh_key' => $organizationSshKey->private_key];
    }
}
