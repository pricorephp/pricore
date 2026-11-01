<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\ExistingVersionData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Services\TagFormats\TagFormat;
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
     *
     * With a tag format, tags that release nothing are dropped, and each ref is
     * compared with the versions it created itself: several packages can share
     * a version string, each from a tag of its own.
     */
    public function handle(RefsCollectionData $refs, Repository $repository): RefsCollectionData
    {
        $format = TagFormat::forRepository($repository);

        if ($format !== null) {
            $refs = $this->withoutIgnoredTags($refs, $format);
        }

        $existingVersions = $this->getExistingVersionLookup($repository, groupBySourceTag: $format !== null);
        $emptyRefs = $repository->emptyRefs()->pluck('commit', 'name');

        // Nothing synced yet, so every ref is new
        if ($existingVersions->isEmpty() && $emptyRefs->isEmpty()) {
            return $refs;
        }

        $filterChanged = fn (RefData $ref): bool => $this->hasChanged($ref, $existingVersions, $emptyRefs, $format !== null);

        $filteredTags = collect($refs->tags->toCollection())->filter($filterChanged)->values();
        $filteredBranches = collect($refs->branches->toCollection())->filter($filterChanged)->values();
        $filteredAll = $filteredTags->merge($filteredBranches);

        return new RefsCollectionData(
            tags: new DataCollection(RefData::class, $filteredTags->all()),
            branches: new DataCollection(RefData::class, $filteredBranches->all()),
            all: new DataCollection(RefData::class, $filteredAll->all()),
        );
    }

    protected function withoutIgnoredTags(RefsCollectionData $refs, TagFormat $format): RefsCollectionData
    {
        $tags = collect($refs->tags->toCollection())
            ->filter(fn (RefData $ref) => TagFormat::target($format, $ref) !== null)
            ->values();
        $branches = collect($refs->branches->toCollection());

        return new RefsCollectionData(
            tags: new DataCollection(RefData::class, $tags->all()),
            branches: new DataCollection(RefData::class, $branches->all()),
            all: new DataCollection(RefData::class, $tags->merge($branches)->all()),
        );
    }

    /**
     * Existing package versions grouped by version string, or by the ref that
     * created them. A monorepo yields one row per package for the same tag, so
     * the lookup keeps all of them.
     *
     * @return Collection<string, Collection<int, ExistingVersionData>>
     */
    protected function getExistingVersionLookup(Repository $repository, bool $groupBySourceTag = false): Collection
    {
        $packageUuids = $repository->packages()->pluck('uuid');

        if ($packageUuids->isEmpty()) {
            return collect();
        }

        $retryFailedDists = (bool) config('pricore.dist.enabled');

        return PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->whereNotNull('source_reference')
            ->get(['version', 'source_reference', 'source_tag', 'dist_failed_at'])
            ->map(fn (PackageVersion $pv) => new ExistingVersionData(
                version: $pv->version,
                sourceReference: (string) $pv->source_reference,
                distFailed: $retryFailedDists && $pv->dist_failed_at !== null,
                sourceTag: $pv->source_tag,
            ))
            ->groupBy($groupBySourceTag ? 'sourceTag' : 'version');
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
    protected function hasChanged(RefData $ref, Collection $existingVersions, Collection $emptyRefs, bool $groupedBySourceTag): bool
    {
        $existing = $existingVersions->get($groupedBySourceTag ? $ref->name : ComposerMetadataData::extractVersion($ref->name));

        if ($existing === null || $existing->isEmpty()) {
            return $emptyRefs->get($ref->name) !== $ref->commit;
        }

        return $existing->contains(
            fn (ExistingVersionData $existingVersion) => $existingVersion->distFailed
                || $existingVersion->sourceReference !== $ref->commit
        );
    }
}
