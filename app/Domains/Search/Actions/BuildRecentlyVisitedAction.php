<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Contracts\Data\SearchPackageData;
use App\Domains\Search\Contracts\Data\SearchRepositoryData;
use App\Http\Data\RecentlyVisitedData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;

class BuildRecentlyVisitedAction
{
    public const int LIMIT = 5;

    public function handle(User $user, Organization $organization): RecentlyVisitedData
    {
        $packages = $organization->packages()
            ->join('package_views', 'packages.uuid', '=', 'package_views.package_uuid')
            ->where('package_views.user_uuid', $user->uuid)
            ->orderByDesc('package_views.last_viewed_at')
            ->select('packages.*')
            ->with('organization:uuid,name,slug')
            ->limit(static::LIMIT)
            ->get()
            ->map(fn (Package $package) => SearchPackageData::fromModel($package))
            ->all();

        $repositories = $organization->repositories()
            ->join('repository_views', 'repositories.uuid', '=', 'repository_views.repository_uuid')
            ->where('repository_views.user_uuid', $user->uuid)
            ->orderByDesc('repository_views.last_viewed_at')
            ->select('repositories.*')
            ->with('organization:uuid,name,slug')
            ->limit(static::LIMIT)
            ->get()
            ->map(fn (Repository $repository) => SearchRepositoryData::fromModel($repository))
            ->all();

        return new RecentlyVisitedData(
            packages: $packages,
            repositories: $repositories,
        );
    }
}
