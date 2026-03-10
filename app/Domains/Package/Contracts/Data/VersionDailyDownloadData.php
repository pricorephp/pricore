<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Organization\Contracts\Data\DailyDownloadData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
class VersionDailyDownloadData extends Data
{
    /**
     * @param  array<int, DailyDownloadData>  $dailyDownloads
     */
    public function __construct(
        public string $version,
        #[TypeScriptType('array<'.DailyDownloadData::class.'>')]
        public array $dailyDownloads,
    ) {}
}
