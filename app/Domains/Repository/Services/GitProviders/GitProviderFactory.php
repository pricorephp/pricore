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
            GitProvider::Bitbucket => new BitbucketProvider($repository->repo_identifier, $credentials),
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

        return static::resolveCredential($repository)->credentials ?? [];
    }

    /**
     * Repositories created before credentials were linked directly fall back to the
     * connecting user's credential, but only when exactly one serves the repository's host.
     */
    public static function resolveCredential(Repository $repository): ?UserGitCredential
    {
        if ($repository->git_credential_uuid) {
            return $repository->gitCredential;
        }

        if (! $repository->credential_user_uuid) {
            return null;
        }

        $candidates = UserGitCredential::query()
            ->where('user_uuid', $repository->credential_user_uuid)
            ->where('provider', $repository->provider)
            ->get()
            ->filter(fn (UserGitCredential $credential) => $credential->servesBaseUrl($repository->custom_base_url));

        return $candidates->count() === 1 ? $candidates->first() : null;
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

        if ($organizationSshKey->organization_uuid !== $repository->organization_uuid) {
            throw new GitProviderException('The SSH key does not belong to the repository organization.');
        }

        return ['ssh_key' => $organizationSshKey->private_key];
    }
}
