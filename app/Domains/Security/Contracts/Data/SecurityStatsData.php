<?php

namespace App\Domains\Security\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SecurityStatsData extends Data
{
    public function __construct(
        public int $affectedPackages,
        public int $totalVulnerabilities,
        public int $criticalCount,
        public int $highCount,
        public int $mediumCount,
        public int $lowCount,
    ) {}
}
