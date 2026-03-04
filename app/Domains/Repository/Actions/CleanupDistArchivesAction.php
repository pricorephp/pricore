<?php

namespace App\Domains\Repository\Actions;

use App\Models\Package;
use App\Models\PackageVersion;

class CleanupDistArchivesAction
{
    public function __construct(
        protected RemoveDistArchiveTask $removeDistArchive,
    ) {}

    /**
     * @return array{packages: int, archives_removed: int}
     */
    public function handle(): array
    {
        $packagesProcessed = 0;
        $archivesRemoved = 0;

        Package::query()
            ->where('dist_keep_last_releases', '>', 0)
            ->lazyById(100, 'uuid')
            ->each(function (Package $package) use (&$packagesProcessed, &$archivesRemoved) {
                $keepCount = $package->dist_keep_last_releases;

                $versionsToClean = PackageVersion::query()
                    ->where('package_uuid', $package->uuid)
                    ->whereNotNull('dist_path')
                    ->stable()
                    ->orderBySemanticVersion('desc')
                    ->skip($keepCount)
                    ->take(PHP_INT_MAX)
                    ->get();

                foreach ($versionsToClean as $version) {
                    $this->removeDistArchive->handle($version);
                    $archivesRemoved++;
                }

                if ($versionsToClean->isNotEmpty()) {
                    $packagesProcessed++;
                }
            });

        return [
            'packages' => $packagesProcessed,
            'archives_removed' => $archivesRemoved,
        ];
    }
}
