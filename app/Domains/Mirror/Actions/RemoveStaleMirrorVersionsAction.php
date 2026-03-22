<?php

namespace App\Domains\Mirror\Actions;

use App\Models\Mirror;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Log;

class RemoveStaleMirrorVersionsAction
{
    /**
     * Remove package versions that no longer exist in the upstream registry.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $allPackageVersions  packageName => versions
     * @return int Number of versions removed
     */
    public function handle(Mirror $mirror, array $allPackageVersions): int
    {
        $packages = $mirror->packages;

        if ($packages->isEmpty()) {
            return 0;
        }

        $totalDeleted = 0;

        foreach ($packages as $package) {
            $upstreamVersions = array_keys($allPackageVersions[$package->name] ?? []);

            if (empty($upstreamVersions)) {
                continue;
            }

            $deleted = (int) PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->whereNotIn('version', $upstreamVersions)
                ->delete();

            $totalDeleted += $deleted;
        }

        if ($totalDeleted > 0) {
            Log::info('Removed stale mirror package versions', [
                'mirror' => $mirror->name,
                'versions_removed' => $totalDeleted,
            ]);
        }

        return $totalDeleted;
    }
}
