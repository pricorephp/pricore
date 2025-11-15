<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class SyncResultData extends Data
{
    public function __construct(
        public int $added,
        public int $updated,
        public int $skipped,
    ) {}

    public function total(): int
    {
        return $this->added + $this->updated + $this->skipped;
    }

    public function hasChanges(): bool
    {
        return $this->added > 0 || $this->updated > 0;
    }
}
