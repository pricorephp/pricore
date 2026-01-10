<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\ActivityFeedData;
use App\Domains\Organization\Contracts\Data\MemberMetricsData;
use App\Domains\Organization\Contracts\Data\OrganizationStatsData;
use App\Domains\Organization\Contracts\Data\PackageMetricsData;
use App\Domains\Organization\Contracts\Data\RecentReleaseData;
use App\Domains\Organization\Contracts\Data\RecentSyncData;
use App\Domains\Organization\Contracts\Data\RepositoryHealthData;
use App\Domains\Organization\Contracts\Data\TokenMetricsData;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\RepositorySyncLog;
use Illuminate\Support\Facades\DB;

class BuildOrganizationStatsAction
{
    public function handle(Organization $organization): OrganizationStatsData
    {
        return new OrganizationStatsData(
            packagesCount: $organization->packages()->count(),
            repositoriesCount: $organization->repositories()->count(),
            tokensCount: $organization->accessTokens()->count(),
            repositoryHealth: $this->buildRepositoryHealth($organization),
            packageMetrics: $this->buildPackageMetrics($organization),
            tokenMetrics: $this->buildTokenMetrics($organization),
            memberMetrics: $this->buildMemberMetrics($organization),
            activityFeed: $this->buildActivityFeed($organization),
        );
    }

    protected function buildRepositoryHealth(Organization $organization): RepositoryHealthData
    {
        $statusCounts = $organization->repositories()
            ->select('sync_status', DB::raw('COUNT(*) as count'))
            ->groupBy('sync_status')
            ->pluck('count', 'sync_status')
            ->toArray();

        $okCount = $statusCounts[RepositorySyncStatus::Ok->value] ?? 0;
        $failedCount = $statusCounts[RepositorySyncStatus::Failed->value] ?? 0;
        $pendingCount = $statusCounts[RepositorySyncStatus::Pending->value] ?? 0;
        $neverSyncedCount = $statusCounts[''] ?? ($statusCounts[null] ?? 0);

        $total = $okCount + $failedCount + $pendingCount + $neverSyncedCount;
        $successRate = $total > 0 ? round(($okCount / $total) * 100, 1) : 0.0;

        return new RepositoryHealthData(
            okCount: $okCount,
            failedCount: $failedCount,
            pendingCount: $pendingCount + $neverSyncedCount,
            successRate: $successRate,
        );
    }

    protected function buildPackageMetrics(Organization $organization): PackageMetricsData
    {
        $packageUuids = $organization->packages()->pluck('uuid');

        $totalVersions = PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->count();

        $stableVersions = PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->stable()
            ->count();

        $devVersions = PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->dev()
            ->count();

        $visibilityCounts = $organization->packages()
            ->select('visibility', DB::raw('COUNT(*) as count'))
            ->groupBy('visibility')
            ->pluck('count', 'visibility')
            ->toArray();

        return new PackageMetricsData(
            totalVersions: $totalVersions,
            stableVersions: $stableVersions,
            devVersions: $devVersions,
            privatePackages: $visibilityCounts['private'] ?? 0,
            publicPackages: $visibilityCounts['public'] ?? 0,
        );
    }

    protected function buildTokenMetrics(Organization $organization): TokenMetricsData
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $tokens = $organization->accessTokens()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN (expires_at IS NULL OR expires_at > ?) AND last_used_at >= ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN (expires_at IS NULL OR expires_at > ?) AND last_used_at IS NULL THEN 1 ELSE 0 END) as unused,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) as expired
            ', [$now, $thirtyDaysAgo, $now, $now])
            ->first();

        return new TokenMetricsData(
            totalTokens: (int) ($tokens->total ?? 0),
            activeTokens: (int) ($tokens->active ?? 0),
            unusedTokens: (int) ($tokens->unused ?? 0),
            expiredTokens: (int) ($tokens->expired ?? 0),
        );
    }

    protected function buildMemberMetrics(Organization $organization): MemberMetricsData
    {
        $roleCounts = DB::table('organization_users')
            ->where('organization_uuid', $organization->uuid)
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        return new MemberMetricsData(
            totalMembers: (int) array_sum($roleCounts),
            ownerCount: (int) ($roleCounts[OrganizationRole::Owner->value] ?? 0),
            adminCount: (int) ($roleCounts[OrganizationRole::Admin->value] ?? 0),
            memberCount: (int) ($roleCounts[OrganizationRole::Member->value] ?? 0),
        );
    }

    protected function buildActivityFeed(Organization $organization): ActivityFeedData
    {
        $packageUuids = $organization->packages()->pluck('uuid');
        $repositoryUuids = $organization->repositories()->pluck('uuid');

        $recentReleases = PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->with('package:uuid,name')
            ->orderByDesc('released_at')
            ->limit(10)
            ->get()
            ->map(fn ($version) => RecentReleaseData::fromModel($version))
            ->all();

        $recentSyncs = RepositorySyncLog::query()
            ->whereIn('repository_uuid', $repositoryUuids)
            ->with('repository:uuid,name')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => RecentSyncData::fromModel($log))
            ->all();

        return new ActivityFeedData(
            recentReleases: $recentReleases,
            recentSyncs: $recentSyncs,
        );
    }
}
