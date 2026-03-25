<?php

namespace App\Domains\Security\Contracts\Data;

use Spatie\LaravelData\Data;

class AdvisorySyncResultData extends Data
{
    public function __construct(
        public int $advisoriesAdded,
        public int $advisoriesUpdated,
    ) {}

    public function total(): int
    {
        return $this->advisoriesAdded + $this->advisoriesUpdated;
    }

    public function hasChanges(): bool
    {
        return $this->advisoriesAdded > 0 || $this->advisoriesUpdated > 0;
    }
}
