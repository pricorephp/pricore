<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationStatsData extends Data
{
    public function __construct(
        public int $packagesCount,
        public int $repositoriesCount,
        public int $tokensCount,
        public RepositoryHealthData $repositoryHealth,
        public PackageMetricsData $packageMetrics,
        public TokenMetricsData $tokenMetrics,
        public MemberMetricsData $memberMetrics,
        public ActivityFeedData $activityFeed,
    ) {}
}
