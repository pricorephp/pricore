<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\BulkImportResultData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;

class BulkCreateRepositoriesAction
{
    public function __construct(
        protected ExtractRepositoryNameAction $extractRepositoryName,
        protected RegisterWebhookAction $registerWebhook,
        protected ResolveUserGitCredentialAction $resolveUserGitCredentialAction,
    ) {}

    /**
     * Bulk create repositories for an organization.
     *
     * @param  array<int, array{repo_identifier: string}>  $repositories
     */
    public function handle(
        Organization $organization,
        GitProvider $provider,
        array $repositories,
        User $user,
        ?string $gitCredentialUuid = null,
    ): BulkImportResultData {
        $gitCredential = $this->resolveUserGitCredentialAction->handle($user, $provider, $gitCredentialUuid);

        $baseUrl = $gitCredential?->baseUrl();

        $existingIdentifiers = Repository::query()
            ->where('organization_uuid', $organization->uuid)
            ->where('provider', $provider)
            ->get(['repo_identifier', 'custom_base_url'])
            ->filter(fn (Repository $repository) => $gitCredential?->servesBaseUrl($repository->custom_base_url) ?? true)
            ->pluck('repo_identifier')
            ->toArray();

        $created = 0;
        $skipped = 0;
        $webhooksFailed = 0;

        foreach ($repositories as $repoData) {
            $identifier = $repoData['repo_identifier'];

            if (in_array($identifier, $existingIdentifiers)) {
                $skipped++;

                continue;
            }

            $name = $this->extractRepositoryName->handle($identifier, $provider);

            $repository = Repository::create([
                'organization_uuid' => $organization->uuid,
                'credential_user_uuid' => $user->uuid,
                'git_credential_uuid' => $gitCredential?->uuid,
                'name' => $name,
                'provider' => $provider,
                'repo_identifier' => $identifier,
                'custom_base_url' => $baseUrl,
            ]);

            SyncRepositoryJob::dispatch($repository);

            if (! $this->registerWebhook->handle($repository)) {
                $webhooksFailed++;
            }

            $created++;
        }

        return new BulkImportResultData(
            created: $created,
            skipped: $skipped,
            webhooksFailed: $webhooksFailed,
        );
    }
}
