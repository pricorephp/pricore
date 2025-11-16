<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Models\RepositorySyncLog;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SyncLogData extends Data
{
    public function __construct(
        public string $uuid,
        public string $status,
        public string $statusLabel,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $completedAt,
        public ?string $errorMessage,
        public int $versionsAdded,
        public int $versionsUpdated,
        /** @var array<string, mixed>|null */
        public ?array $details,
    ) {}

    public static function fromModel(RepositorySyncLog $syncLog): self
    {
        return new self(
            uuid: $syncLog->uuid,
            status: $syncLog->status->value,
            statusLabel: $syncLog->status->label(),
            startedAt: $syncLog->started_at,
            completedAt: $syncLog->completed_at,
            errorMessage: $syncLog->error_message,
            versionsAdded: $syncLog->versions_added,
            versionsUpdated: $syncLog->versions_updated,
            details: $syncLog->details,
        );
    }
}
