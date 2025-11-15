<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class RefData extends Data
{
    public function __construct(
        public string $name,
        public string $commit,
    ) {}
}
