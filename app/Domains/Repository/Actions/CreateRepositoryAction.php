<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;

class CreateRepositoryAction
{
    public function __construct(
        protected ExtractRepositoryNameAction $extractRepositoryName,
        protected RegisterWebhookAction $registerWebhook,
        protected RecordActivityTask $recordActivity,
    ) {}

    public function handle(
        Organization $organization,
        GitProvider $provider,
        string $repoIdentifier,
        User $user,
        ?string $name = null,
        ?string $defaultBranch = null,
        ?string $sshKeyUuid = null,
    ): Repository {
        $name ??= $this->extractRepositoryName->handle($repoIdentifier, $provider);

        $isGenericGit = $provider === GitProvider::Git;

        $repository = Repository::create([
            'organization_uuid' => $organization->uuid,
            'credential_user_uuid' => $isGenericGit ? null : $user->uuid,
            'ssh_key_uuid' => $isGenericGit ? $sshKeyUuid : null,
            'name' => $name,
            'provider' => $provider,
            'repo_identifier' => $repoIdentifier,
            'custom_base_url' => $this->resolveBaseUrl($provider, $user->uuid),
            'default_branch' => $defaultBranch,
        ]);

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::RepositoryAdded,
            subject: $repository,
            actor: $user,
            properties: ['name' => $repository->name, 'provider' => $repository->provider->value],
        );

        SyncRepositoryJob::dispatch($repository);

        $this->registerWebhook->handle($repository);

        return $repository;
    }

    private function resolveBaseUrl(GitProvider $provider, string $userUuid): ?string
    {
        if (! $provider->supportsSelfHosted()) {
            return null;
        }

        $credential = UserGitCredential::query()
            ->where('user_uuid', $userUuid)
            ->where('provider', $provider)
            ->first();

        return $credential?->credentials['url'] ?? null;
    }
}
