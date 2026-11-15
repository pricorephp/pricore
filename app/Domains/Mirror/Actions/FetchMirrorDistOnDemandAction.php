<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Exceptions\MirrorDistDownloadException;
use App\Domains\Repository\Actions\RecordDistArchiveAction;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FetchMirrorDistOnDemandAction
{
    public function __construct(
        protected DownloadMirrorDistAction $downloadMirrorDistAction,
        protected RecordDistArchiveAction $recordDistArchiveAction,
    ) {}

    /**
     * Download and store the archive of a mirrored version on its first request.
     *
     * Only the reference the version currently points at can be fetched, since
     * that is the only one the upstream dist URL is known for. Returns the stored
     * path, or null when the version is not served on demand.
     *
     * @throws MirrorDistDownloadException
     * @throws LockTimeoutException
     */
    public function handle(
        Organization $organization,
        string $packageName,
        string $version,
        string $reference,
    ): ?string {
        $package = Package::query()
            ->where('organization_uuid', $organization->uuid)
            ->where('name', $packageName)
            ->whereNotNull('mirror_uuid')
            ->with('mirror')
            ->first();

        $mirror = $package?->mirror;

        if (! $package || ! $mirror || ! $mirror->downloadsDistsOnDemand()) {
            return null;
        }

        $packageVersion = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->matchingVersion($version)
            ->where('source_reference', $reference)
            ->get()
            ->sortByDesc(fn (PackageVersion $packageVersion): bool => $packageVersion->version === $version)
            ->first();

        if (! $packageVersion || ! $packageVersion->fetchesDistOnDemand()) {
            return null;
        }

        // Concurrent installs of the same version wait for the first download
        // instead of each fetching the archive from upstream.
        return Cache::lock("mirror-dist:{$packageVersion->uuid}:{$reference}", 300)
            ->block(120, function () use ($organization, $mirror, $package, $packageVersion, $reference): ?string {
                // A sync may have moved the version while this request waited.
                // Its dist URL then points at another commit's archive.
                $packageVersion->refresh();

                if ($packageVersion->source_reference !== $reference) {
                    return null;
                }

                $existingArchive = $packageVersion->archives()
                    ->where('source_reference', $reference)
                    ->first();

                if ($existingArchive && Storage::disk(config('pricore.dist.disk'))->exists($existingArchive->path)) {
                    return $existingArchive->path;
                }

                $distArchiveData = $this->downloadMirrorDistAction->handle(
                    $mirror,
                    $packageVersion,
                    $package,
                    $organization->slug,
                );

                if (! $distArchiveData) {
                    return null;
                }

                return $this->recordDownloadedArchive($packageVersion, $distArchiveData, $reference, $organization->slug);
            });
    }

    /**
     * A sync can also move the version during the download itself. The archive
     * then belongs to a commit the version no longer points at, so it is kept
     * detached for lock files pinning that commit rather than made current.
     */
    protected function recordDownloadedArchive(
        PackageVersion $packageVersion,
        DistArchiveData $distArchiveData,
        string $reference,
        string $organizationSlug,
    ): ?string {
        return DB::transaction(function () use ($packageVersion, $distArchiveData, $reference, $organizationSlug): ?string {
            $currentVersion = PackageVersion::query()
                ->whereKey($packageVersion->uuid)
                ->lockForUpdate()
                ->first();

            if (! $currentVersion) {
                return null;
            }

            if ($currentVersion->source_reference === $reference) {
                return $this->recordDistArchiveAction
                    ->handle($currentVersion, $distArchiveData, $organizationSlug)
                    ?->path;
            }

            return DistArchive::query()->updateOrCreate(
                [
                    'package_version_uuid' => $currentVersion->uuid,
                    'source_reference' => $reference,
                ],
                [
                    'package_uuid' => $currentVersion->package_uuid,
                    'path' => $distArchiveData->path,
                    'shasum' => $distArchiveData->shasum,
                    'size' => $distArchiveData->size,
                    'detached_at' => now(),
                ],
            )->path;
        });
    }
}
