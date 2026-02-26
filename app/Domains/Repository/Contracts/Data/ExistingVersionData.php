<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class ExistingVersionData extends Data
{
    public function __construct(
        public string $version,
        public string $sourceReference,
    ) {}

    public function matches(string $version, string $commit): bool
    {
        return $this->version === $version && $this->sourceReference === $commit;
    }
}
