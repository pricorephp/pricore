<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\RepositorySyncLog;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RecentSyncData extends Data
{
    public function __construct(
        public string $repositoryName,
        public string $repositoryUuid,
        public SyncStatus $status,
        public string $statusLabel,
        public CarbonInterface $startedAt,
        public int $versionsAdded,
        public int $versionsUpdated,
    ) {}

    public static function fromModel(RepositorySyncLog $log): self
    {
        return new self(
            repositoryName: $log->repository->name,
            repositoryUuid: $log->repository->uuid,
            status: $log->status,
            statusLabel: $log->status->label(),
            startedAt: $log->started_at,
            versionsAdded: $log->versions_added,
            versionsUpdated: $log->versions_updated,
        );
    }
}
