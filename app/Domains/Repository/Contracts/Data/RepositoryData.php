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
        public string $repoIdentifier,
        public ?string $syncStatus,
        public ?CarbonInterface $lastSyncedAt,
        public int $packagesCount,
    ) {}

    public static function fromModel(Repository $repository): self
    {
        return new self(
            uuid: $repository->uuid,
            name: $repository->name,
            provider: $repository->provider->value,
            repoIdentifier: $repository->repo_identifier,
            syncStatus: $repository->sync_status?->value,
            lastSyncedAt: $repository->last_synced_at,
            packagesCount: $repository->packages_count ?? 0,
        );
    }
}
