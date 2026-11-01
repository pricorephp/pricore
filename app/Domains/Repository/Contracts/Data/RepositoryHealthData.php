<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
class RepositoryHealthData extends Data
{
    /**
     * @param  array<int, RecentSyncData>  $recentSyncs  Newest first
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $provider,
        public string $repoIdentifier,
        public ?RepositorySyncStatus $syncStatus,
        public ?string $syncStatusLabel,
        public ?CarbonInterface $lastSyncedAt,
        public int $packagesCount,
        #[TypeScriptType('array<'.RecentSyncData::class.'>')]
        public array $recentSyncs,
    ) {}
}
