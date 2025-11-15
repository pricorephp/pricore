<?php

namespace App\Domains\Composer\Contracts\Data;

use Spatie\LaravelData\Data;

class SourceData extends Data
{
    public function __construct(
        public string $type,
        public string $url,
        public ?string $reference = null,
    ) {}
}
