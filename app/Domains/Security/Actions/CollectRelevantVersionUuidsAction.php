<?php

namespace App\Domains\Security\Actions;

use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

class CollectRelevantVersionUuidsAction
{
    /**
     * Latest stable version per package (what's in production) plus all dev versions (active branches).
     *
     * @return array<int, string>
     */
    public function handle(Organization $organization): array
    {
        $packageUuids = Package::where('organization_uuid', $organization->uuid)
            ->pluck('uuid');

        $devVersionUuids = PackageVersion::whereIn('package_uuid', $packageUuids)
            ->dev()
            ->pluck('uuid')
            ->all();

        $latestStableUuids = PackageVersion::whereIn('package_uuid', $packageUuids)
            ->stable()
            ->orderBySemanticVersion('desc')
            ->get(['uuid', 'package_uuid'])
            ->groupBy('package_uuid')
            ->map(fn ($versions) => $versions->first()?->uuid)
            ->values()
            ->all();

        return array_merge($latestStableUuids, $devVersionUuids);
    }
}
