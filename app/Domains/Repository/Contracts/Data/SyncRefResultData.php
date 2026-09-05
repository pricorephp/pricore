<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class SyncRefResultData extends Data
{
    public function __construct(
        public int $added = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $removed = 0,
        public int $packagesFound = 0,
    ) {}
}
