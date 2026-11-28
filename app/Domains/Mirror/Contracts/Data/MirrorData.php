<?php

namespace App\Domains\Mirror\Contracts\Data;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Mirror\Contracts\Enums\MirrorSyncMode;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MirrorData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $url,
        public MirrorSyncMode $syncMode,
        public MirrorAuthType $authType,
        public bool $mirrorDist,
        public bool $lazyDist,
        public ?RepositorySyncStatus $syncStatus,
        public ?string $lastSyncedAt,
        public int $packagesCount,
        public string $createdAt,
    ) {}

    public static function fromModel(Mirror $mirror): self
    {
        return new self(
            uuid: $mirror->uuid,
            name: $mirror->name,
            url: $mirror->url,
            syncMode: $mirror->sync_mode,
            authType: $mirror->auth_type,
            mirrorDist: $mirror->mirror_dist,
            lazyDist: $mirror->lazy_dist,
            syncStatus: $mirror->sync_status,
            lastSyncedAt: $mirror->last_synced_at?->toISOString(),
            packagesCount: $mirror->packages_count ?? $mirror->packages()->count(),
            createdAt: $mirror->created_at?->toISOString() ?? '',
        );
    }
}
