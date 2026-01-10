<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RepositoryHealthData extends Data
{
    public function __construct(
        public int $okCount,
        public int $failedCount,
        public int $pendingCount,
        public float $successRate,
    ) {}
}
