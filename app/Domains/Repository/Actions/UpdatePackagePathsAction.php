<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Contracts\Data\UpdatePackagePathsResultData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdatePackagePathsAction
{
    public function __construct(
        protected PurgeDistArchiveFilesTask $purgeDistArchiveFilesTask,
        protected RecordActivityTask $recordActivityTask,
    ) {}

    /**
     * Store new package paths for a repository. Packages whose directory the new
     * paths no longer select are removed, and a forced sync picks up the packages
     * the new paths do select.
     *
     * @param  array<int, string>|null  $patterns
     */
    public function handle(Repository $repository, ?array $patterns, ?User $actor = null): UpdatePackagePathsResultData
    {
        if (self::sorted($patterns) === self::sorted($repository->package_paths ?: null)) {
            return new UpdatePackagePathsResultData(changed: false, packagesRemoved: 0);
        }

        $removed = DB::transaction(function () use ($repository, $patterns, $actor): int {
            $removed = $this->removeUnselectedPackages($repository, $patterns, $actor);

            // The forced sync must happen even when this dispatch is dropped
            // because a sync is already running; the next sync honours this.
            $repository->update([
                'package_paths' => $patterns,
                'full_sync_requested_at' => now(),
                'sync_status' => RepositorySyncStatus::Pending,
            ]);

            return $removed;
        });

        SyncRepositoryJob::dispatch($repository, true);

        return new UpdatePackagePathsResultData(changed: true, packagesRemoved: $removed);
    }

    /**
     * A package goes only when none of its versions lives under the new paths.
     * One that moved directories between tags keeps its matching versions; the
     * forced sync drops the others ref by ref.
     *
     * @param  array<int, string>|null  $patterns
     */
    protected function removeUnselectedPackages(Repository $repository, ?array $patterns, ?User $actor): int
    {
        $selected = $patterns ?? [PackagePathPattern::ROOT];

        $packages = $repository->packages()->get();

        $versionPaths = PackageVersion::query()
            ->whereIn('package_uuid', $packages->pluck('uuid'))
            ->distinct()
            ->get(['package_uuid', 'source_path'])
            ->groupBy('package_uuid')
            ->map(fn (Collection $versions) => $versions->pluck('source_path'));

        $packages = $packages->reject(function (Package $package) use ($selected, $versionPaths): bool {
            $paths = $versionPaths->get($package->uuid) ?? collect([$package->source_path]);

            return $paths->contains(fn (?string $path) => PackagePathPattern::anyMatches($selected, $path));
        });

        if ($packages->isEmpty()) {
            return 0;
        }

        // Versions and archive rows cascade at the database level once a package
        // goes, which fires no model events. Clear the files while the rows exist.
        $this->purgeDistArchiveFilesTask->handle($packages->pluck('uuid'));

        $organization = $repository->organization()->first();

        foreach ($packages as $package) {
            if ($organization) {
                $this->recordActivityTask->handle(
                    organization: $organization,
                    type: ActivityType::PackageRemoved,
                    subject: $package,
                    actor: $actor,
                    properties: [
                        'name' => $package->name,
                        'source_path' => $package->source_path,
                        'reason' => 'package_paths_changed',
                    ],
                );
            }

            $package->delete();
        }

        return $packages->count();
    }

    /**
     * @param  array<int, string>|null  $patterns
     * @return array<int, string>|null
     */
    protected static function sorted(?array $patterns): ?array
    {
        if ($patterns === null) {
            return null;
        }

        sort($patterns);

        return $patterns;
    }
}
