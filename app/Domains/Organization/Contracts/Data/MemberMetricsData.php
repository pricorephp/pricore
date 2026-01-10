<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MemberMetricsData extends Data
{
    public function __construct(
        public int $totalMembers,
        public int $ownerCount,
        public int $adminCount,
        public int $memberCount,
    ) {}
}
