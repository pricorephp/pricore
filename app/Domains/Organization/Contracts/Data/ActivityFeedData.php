<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ActivityFeedData extends Data
{
    /**
     * @param  array<int, RecentReleaseData>  $recentReleases
     * @param  array<int, RecentSyncData>  $recentSyncs
     */
    public function __construct(
        public array $recentReleases,
        public array $recentSyncs,
    ) {}
}
