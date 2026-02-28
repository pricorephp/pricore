<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\OrganizationStatsData;
use App\Models\Organization;

class BuildOrganizationStatsAction
{
    public function __construct(
        protected BuildOrganizationDownloadStatsAction $downloadStats,
    ) {}

    public function handle(Organization $organization): OrganizationStatsData
    {
        $downloads = $this->downloadStats->handle($organization);

        return new OrganizationStatsData(
            packagesCount: $organization->packages()->count(),
            repositoriesCount: $organization->repositories()->count(),
            tokensCount: $organization->accessTokens()->count(),
            membersCount: $organization->members()->count(),
            totalDownloads: $downloads['totalDownloads'],
            dailyDownloads: $downloads['dailyDownloads'],
        );
    }
}
