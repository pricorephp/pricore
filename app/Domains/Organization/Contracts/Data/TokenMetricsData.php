<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TokenMetricsData extends Data
{
    public function __construct(
        public int $totalTokens,
        public int $activeTokens,
        public int $unusedTokens,
        public int $expiredTokens,
    ) {}
}
