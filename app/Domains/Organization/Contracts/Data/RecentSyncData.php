<?php

namespace App\Domains\Organization\Contracts\Data;

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
        public string $status,
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
            status: $log->status->value,
            statusLabel: $log->status->label(),
            startedAt: $log->started_at,
            versionsAdded: $log->versions_added,
            versionsUpdated: $log->versions_updated,
        );
    }
}
