<?php

namespace App\Domains\Composer\Actions;

use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;

class ResolveDistArchiveAction
{
    /**
     * Resolve the stored archive for a requested version and commit reference.
     *
     * A branch keeps a single version row that every sync moves to the new head,
     * so the requested commit is often one the row has already moved past. Every
     * archive is recorded against its own commit, which keeps those lock files
     * installable.
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
            ->first();

        if (! $package) {
            return null;
        }

        $packageVersions = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->matchingVersion($version)
            ->with(['archives' => fn (Relation $query) => $query->where('source_reference', $reference)])
            ->get();

        if ($packageVersions->isEmpty()) {
            return null;
        }

        // Rows matching the request exactly win over the v-prefix variants
        // scopeMatchingVersion also returns.
        $candidates = $this->orderByExactVersion($packageVersions, $version);

        $disk = Storage::disk(config('pricore.dist.disk'));

        foreach ($candidates as $candidate) {
            $archive = $candidate->archives->first();

            if ($archive && $disk->exists($archive->path)) {
                return $archive->path;
            }
        }

        // Archives written before the dist_archives backfill
        // (2026_08_29_000002_backfill_dist_archives) that their branch had
        // already moved past have no row, because only the version's current
        // dist_path could be backfilled. Their storage path is deterministic,
        // so recover them by name. Removable once no supported upgrade path
        // predates that migration.
        foreach ($candidates as $candidate) {
            $legacyPath = DistArchiveData::pathFor(
                organizationSlug: $organization->slug,
                packageName: $packageName,
                version: $candidate->version,
                reference: $reference,
            );

            if ($disk->exists($legacyPath)) {
                return $legacyPath;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, PackageVersion>  $packageVersions
     * @return Collection<int, PackageVersion>
     */
    protected function orderByExactVersion(Collection $packageVersions, string $version): Collection
    {
        return $packageVersions
            ->sortByDesc(fn (PackageVersion $packageVersion): bool => $packageVersion->version === $version)
            ->values();
    }
}
