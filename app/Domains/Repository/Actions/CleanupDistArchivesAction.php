<?php

namespace App\Domains\Repository\Actions;

use App\Models\DistArchive;
use App\Models\Package;
use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Storage;

class CleanupDistArchivesAction
{
    public function __construct(
        protected RemoveDistArchiveTask $removeDistArchive,
    ) {}

    /**
     * @return array{packages: int, archives_removed: int, detached_marked: int, detached_removed: int}
     */
    public function handle(): array
    {
        $detachedMarked = $this->repairDetachedArchives();

        [$packagesProcessed, $archivesRemoved] = $this->applyReleaseRetention();

        $detachedRemoved = $this->applyDetachedRetention();

        return [
            'packages' => $packagesProcessed,
            'archives_removed' => $archivesRemoved,
            'detached_marked' => $detachedMarked,
            'detached_removed' => $detachedRemoved,
        ];
    }

    /**
     * Repair archives that stopped being current without going through
     * DetachDistArchivesTask, then clear pointers left describing them.
     *
     * The dist_* columns are a write-through cache; this is what keeps them
     * honest when a sync dies between moving the reference and recording the
     * new archive.
     *
     * @return int Number of archives newly marked detached
     */
    protected function repairDetachedArchives(): int
    {
        $marked = DistArchive::query()
            ->whereNull('detached_at')
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->from('package_versions')
                ->whereColumn('package_versions.uuid', 'dist_archives.package_version_uuid')
                ->whereColumn('package_versions.source_reference', 'dist_archives.source_reference'))
            ->update(['detached_at' => now()]);

        PackageVersion::query()
            ->whereNotNull('dist_path')
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->from('dist_archives')
                ->whereColumn('dist_archives.package_version_uuid', 'package_versions.uuid')
                ->whereNull('dist_archives.detached_at'))
            ->update([
                'dist_url' => null,
                'dist_path' => null,
                'dist_shasum' => null,
                'dist_size' => null,
            ]);

        return $marked;
    }

    /**
     * Per-package retention of stable release archives.
     *
     * @return array{0: int, 1: int} Packages processed, archives removed
     */
    protected function applyReleaseRetention(): array
    {
        $packagesProcessed = 0;
        $archivesRemoved = 0;

        // Uploaded archives are the only copy and can't be rebuilt from a source
        Package::query()
            ->where('dist_keep_last_releases', '>', 0)
            ->where('is_artifact', false)
            ->lazyById(100, 'uuid')
            ->each(function (Package $package) use (&$packagesProcessed, &$archivesRemoved) {
                $keepCount = $package->dist_keep_last_releases;

                $downloadedAfter = $this->downloadedAfter();

                $versionsToClean = PackageVersion::query()
                    ->where('package_uuid', $package->uuid)
                    ->whereNotNull('dist_path')
                    ->stable()
                    ->orderBySemanticVersion('desc')
                    ->skip($keepCount)
                    ->take(PHP_INT_MAX)
                    ->withMax('archives', 'last_downloaded_at')
                    ->get()
                    // Filtered after skipping, so a recent download never shifts
                    // which releases count as the latest ones
                    ->reject(function (PackageVersion $version) use ($downloadedAfter): bool {
                        $lastDownloadedAt = $version->getAttribute('archives_max_last_downloaded_at');

                        return $downloadedAfter !== null
                            && $lastDownloadedAt !== null
                            && $downloadedAfter->lessThan($lastDownloadedAt);
                    });

                foreach ($versionsToClean as $version) {
                    $this->removeDistArchive->handle($version);
                    $archivesRemoved++;
                }

                if ($versionsToClean->isNotEmpty()) {
                    $packagesProcessed++;
                }
            });

        return [$packagesProcessed, $archivesRemoved];
    }

    /**
     * Prune archives whose branch moved on, once they are older than the
     * configured window. Unset means keep them indefinitely.
     *
     * @return int Number of detached archives removed
     */
    protected function applyDetachedRetention(): int
    {
        $keepDays = $this->keepDetachedDays();

        if ($keepDays === 0) {
            return 0;
        }

        $disk = Storage::disk(config('pricore.dist.disk'));
        $cutoff = now()->subDays($keepDays);
        $removed = 0;

        $downloadedAfter = $this->downloadedAfter();

        DistArchive::query()
            ->detached()
            ->where('detached_at', '<', $cutoff)
            ->when($downloadedAfter, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('last_downloaded_at')
                ->orWhere('last_downloaded_at', '<=', $downloadedAfter)))
            ->lazyById(500, 'uuid')
            ->each(function (DistArchive $archive) use ($disk, &$removed) {
                $disk->delete($archive->path);
                $archive->delete();
                $removed++;
            });

        return $removed;
    }

    /**
     * Archives downloaded after this moment are still in use. Null when the
     * protection is turned off.
     */
    protected function downloadedAfter(): ?CarbonInterface
    {
        $keepDays = config('pricore.dist.keep_downloaded_days');

        if (! is_numeric($keepDays) || (int) $keepDays <= 0) {
            return null;
        }

        // One day more: downloads are recorded at most once a day, so the last
        // one can be up to a day older than the latest download
        return now()->subDays((int) $keepDays + 1);
    }

    protected function keepDetachedDays(): int
    {
        $keepDays = config('pricore.dist.keep_detached_days');

        if (! is_numeric($keepDays) || (int) $keepDays <= 0) {
            return 0;
        }

        return (int) $keepDays;
    }
}
