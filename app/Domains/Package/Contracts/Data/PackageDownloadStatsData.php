<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Organization\Contracts\Data\DailyDownloadData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageDownloadStatsData extends Data
{
    /**
     * @param  array<int, DailyDownloadData>  $dailyDownloads
     * @param  array<int, VersionDownloadData>  $versionBreakdown
     */
    public function __construct(
        public int $totalDownloads,
        public array $dailyDownloads,
        public array $versionBreakdown,
    ) {}
}
