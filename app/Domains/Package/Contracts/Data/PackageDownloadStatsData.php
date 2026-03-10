<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Organization\Contracts\Data\DailyDownloadData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
class PackageDownloadStatsData extends Data
{
    /**
     * @param  array<int, DailyDownloadData>  $dailyDownloads
     * @param  array<int, VersionDailyDownloadData>  $versionDailyDownloads
     */
    public function __construct(
        public int $totalDownloads,
        #[TypeScriptType('array<'.DailyDownloadData::class.'>')]
        public array $dailyDownloads,
        #[TypeScriptType('array<'.VersionDailyDownloadData::class.'>')]
        public array $versionDailyDownloads,
    ) {}
}
