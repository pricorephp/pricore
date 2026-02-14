<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Models\Repository;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RepositoryData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $provider,
        public string $providerLabel,
        public string $repoIdentifier,
        public ?string $url,
        public ?string $syncStatus,
        public ?string $syncStatusLabel,
        public ?CarbonInterface $lastSyncedAt,
        public int $packagesCount,
        public bool $webhookActive,
    ) {}

    public static function fromModel(Repository $repository): self
    {
        return new self(
            uuid: $repository->uuid,
            name: $repository->name,
            provider: $repository->provider->value,
            providerLabel: $repository->provider->label(),
            repoIdentifier: $repository->repo_identifier,
            url: $repository->provider->repositoryUrl($repository->repo_identifier),
            syncStatus: $repository->sync_status?->value,
            syncStatusLabel: $repository->sync_status?->label(),
            lastSyncedAt: $repository->last_synced_at,
            packagesCount: $repository->packages_count ?? 0,
            webhookActive: $repository->webhook_id !== null,
        );
    }
}
