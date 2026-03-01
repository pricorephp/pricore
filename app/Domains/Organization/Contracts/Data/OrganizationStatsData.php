<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationStatsData extends Data
{
    /**
     * @param  array<int, DailyDownloadData>  $dailyDownloads
     */
    public function __construct(
        public int $packagesCount,
        public int $repositoriesCount,
        public int $tokensCount,
        public int $membersCount,
        public int $totalDownloads,
        public array $dailyDownloads,
    ) {}
}
