<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\BulkImportResultData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\UserGitCredential;

class BulkCreateRepositoriesAction
{
    public function __construct(
        protected ExtractRepositoryNameAction $extractRepositoryName,
        protected RegisterWebhookAction $registerWebhook,
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
        string $userUuid,
    ): BulkImportResultData {
        $existingIdentifiers = Repository::query()
            ->where('organization_uuid', $organization->uuid)
            ->where('provider', $provider)
            ->pluck('repo_identifier')
            ->toArray();

        $baseUrl = $this->resolveBaseUrl($provider, $userUuid);

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
                'credential_user_uuid' => $userUuid,
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
