<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RecentSyncData extends Data
{
    public function __construct(
        public string $uuid,
        public SyncStatus $status,
        public string $statusLabel,
        public CarbonInterface $startedAt,
    ) {}
}
