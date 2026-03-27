<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class DistArchiveData extends Data
{
    public function __construct(
        public string $path,
        public string $shasum,
        public int $size,
    ) {}
}
