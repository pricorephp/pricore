<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class ExistingVersionData extends Data
{
    public function __construct(
        public string $version,
        public string $sourceReference,
        public bool $distFailed = false,
        public ?string $sourceTag = null,
    ) {}
}
