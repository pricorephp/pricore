<?php

namespace App\Domains\Security\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageSecuritySummaryData extends Data
{
    public function __construct(
        public string $packageUuid,
        public string $packageName,
        public int $affectedVersionCount,
        public int $criticalCount,
        public int $highCount,
        public int $mediumCount,
        public int $lowCount,
        public int $totalCount,
    ) {}
}
