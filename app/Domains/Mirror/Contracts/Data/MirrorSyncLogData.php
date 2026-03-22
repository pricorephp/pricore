<?php

namespace App\Domains\Mirror\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\MirrorSyncLog;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MirrorSyncLogData extends Data
{
    public function __construct(
        public string $uuid,
        public SyncStatus $status,
        public string $statusLabel,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $completedAt,
        public ?string $errorMessage,
        public int $versionsAdded,
        public int $versionsUpdated,
        public int $versionsRemoved,
        /** @var array<string, mixed>|null */
        public ?array $details,
    ) {}

    public static function fromModel(MirrorSyncLog $mirrorSyncLog): self
    {
        return new self(
            uuid: $mirrorSyncLog->uuid,
            status: $mirrorSyncLog->status,
            statusLabel: $mirrorSyncLog->status->label(),
            startedAt: $mirrorSyncLog->started_at,
            completedAt: $mirrorSyncLog->completed_at,
            errorMessage: $mirrorSyncLog->error_message,
            versionsAdded: $mirrorSyncLog->versions_added,
            versionsUpdated: $mirrorSyncLog->versions_updated,
            versionsRemoved: $mirrorSyncLog->versions_removed,
            details: $mirrorSyncLog->details,
        );
    }
}
