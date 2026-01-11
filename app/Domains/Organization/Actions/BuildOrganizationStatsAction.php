<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\ActivityFeedData;
use App\Domains\Organization\Contracts\Data\OrganizationStatsData;
use App\Domains\Organization\Contracts\Data\RecentReleaseData;
use App\Domains\Organization\Contracts\Data\RecentSyncData;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\RepositorySyncLog;

class BuildOrganizationStatsAction
{
    public function handle(Organization $organization): OrganizationStatsData
    {
        return new OrganizationStatsData(
            packagesCount: $organization->packages()->count(),
            repositoriesCount: $organization->repositories()->count(),
            tokensCount: $organization->accessTokens()->count(),
            membersCount: $organization->members()->count(),
            activityFeed: $this->buildActivityFeed($organization),
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
