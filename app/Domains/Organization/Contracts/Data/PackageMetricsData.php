<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageMetricsData extends Data
{
    public function __construct(
        public int $totalVersions,
        public int $stableVersions,
        public int $devVersions,
        public int $privatePackages,
        public int $publicPackages,
    ) {}
}
