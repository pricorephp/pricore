<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\ExistingVersionData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

class FilterChangedRefsAction
{
    /**
     * Filter out refs whose commit SHA hasn't changed since last sync.
     *
     * Compares each ref's computed version string and commit SHA against
     * existing PackageVersion records to avoid unnecessary API calls. Versions
     * whose dist archive failed to build count as changed so it gets retried.
     * A ref without versions is unchanged when it held no composer.json at the
     * same commit last time.
     */
    public function handle(RefsCollectionData $refs, Repository $repository): RefsCollectionData
    {
        $existingVersions = $this->getExistingVersionLookup($repository);
        $emptyRefs = $repository->emptyRefs()->pluck('commit', 'name');

        // Nothing synced yet, so every ref is new
        if ($existingVersions->isEmpty() && $emptyRefs->isEmpty()) {
            return $refs;
        }

        $filterChanged = fn (RefData $ref): bool => $this->hasChanged($ref, $existingVersions, $emptyRefs);

        $filteredTags = collect($refs->tags->toCollection())->filter($filterChanged)->values();
        $filteredBranches = collect($refs->branches->toCollection())->filter($filterChanged)->values();
        $filteredAll = $filteredTags->merge($filteredBranches);

        return new RefsCollectionData(
            tags: new DataCollection(RefData::class, $filteredTags->all()),
            branches: new DataCollection(RefData::class, $filteredBranches->all()),
            all: new DataCollection(RefData::class, $filteredAll->all()),
        );
    }

    /**
     * Existing package versions grouped by version string. A monorepo yields one
     * row per package for the same tag, so the lookup keeps all of them.
     *
     * @return Collection<string, Collection<int, ExistingVersionData>>
     */
    protected function getExistingVersionLookup(Repository $repository): Collection
    {
        $packageUuids = $repository->packages()->pluck('uuid');

        if ($packageUuids->isEmpty()) {
            return collect();
        }

        $retryFailedDists = (bool) config('pricore.dist.enabled');

        return PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->whereNotNull('source_reference')
            ->get(['version', 'source_reference', 'dist_failed_at'])
            ->map(fn (PackageVersion $pv) => new ExistingVersionData(
                version: $pv->version,
                sourceReference: (string) $pv->source_reference,
                distFailed: $retryFailedDists && $pv->dist_failed_at !== null,
            ))
            ->groupBy('version');
    }

    /**
     * A ref is unchanged only when every package synced from it already sits at
     * its commit. A single stale row (a package that failed last time, one
     * configured later, or one whose dist archive failed to build) keeps the
     * ref in the sync.
     *
     * @param  Collection<string, Collection<int, ExistingVersionData>>  $existingVersions
     * @param  Collection<string, string>  $emptyRefs
     */
    protected function hasChanged(RefData $ref, Collection $existingVersions, Collection $emptyRefs): bool
    {
        $version = ComposerMetadataData::extractVersion($ref->name);
        $existing = $existingVersions->get($version);

        if ($existing === null || $existing->isEmpty()) {
            return $emptyRefs->get($ref->name) !== $ref->commit;
        }

        return $existing->contains(
            fn (ExistingVersionData $existingVersion) => $existingVersion->distFailed
                || ! $existingVersion->matches($version, $ref->commit)
        );
    }
}
