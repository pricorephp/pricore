<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DailyDownloadData extends Data
{
    public function __construct(
        public string $date,
        public int $downloads,
    ) {}
}
