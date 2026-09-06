<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class UpdatePackagePathsResultData extends Data
{
    public function __construct(
        public bool $changed,
        public int $packagesRemoved,
    ) {}
}
