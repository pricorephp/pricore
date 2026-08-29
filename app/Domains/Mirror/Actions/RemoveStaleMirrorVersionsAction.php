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

            // One at a time so the deleting hook removes each version's archive
            // files; a bulk delete fires no events and strands them.
            PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->whereNotIn('version', $upstreamVersions)
                ->lazyById(100, 'uuid')
                ->each(function (PackageVersion $version) use (&$totalDeleted) {
                    $version->delete();
                    $totalDeleted++;
                });
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
