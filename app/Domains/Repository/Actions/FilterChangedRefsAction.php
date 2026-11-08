<?php

namespace App\Domains\Repository\Actions;

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
     * a version string, each from a tag of its own. A shared tag also counts as
     * changed when a package's own tag of its version was deleted, so the shared
     * tag releases that package again.
     */
    public function handle(RefsCollectionData $refs, Repository $repository): RefsCollectionData
    {
        $format = TagFormat::forRepository($repository);
        $tagNames = collect($refs->tags->toCollection())->pluck('name')->all();

        if ($format !== null) {
            $refs = $this->withoutIgnoredTags($refs, $format);
        }

        $existing = $this->getExistingVersions($repository);
        $existingVersions = $existing->groupBy($format !== null ? 'sourceTag' : 'version');
        $emptyRefs = $repository->emptyRefs()->pluck('commit', 'name');

        // Nothing synced yet, so every ref is new
        if ($existingVersions->isEmpty() && $emptyRefs->isEmpty()) {
            return $refs;
        }

        $orphanedVersions = $format === null ? [] : $existing
            ->filter(fn (ExistingVersionData $version) => TagFormat::ownedByDeletedPackageTag($format, $version->sourceTag, $version->version, $tagNames))
            ->pluck('version')
            ->unique()
            ->all();

        $filterChanged = fn (RefData $ref): bool => $this->hasChanged($ref, $existingVersions, $emptyRefs, $format)
            || $this->releasesOrphanedVersion($ref, $format, $orphanedVersions);

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
     * @param  array<int, string>  $orphanedVersions
     */
    protected function releasesOrphanedVersion(RefData $ref, ?TagFormat $format, array $orphanedVersions): bool
    {
        if ($format === null || $orphanedVersions === []) {
            return false;
        }

        $target = TagFormat::target($format, $ref);

        return $target !== null && $target->isShared() && in_array($target->version, $orphanedVersions, true);
    }

    /**
     * Existing package versions. A monorepo yields one row per package for the
     * same tag, so all of them are kept.
     *
     * @return Collection<int, ExistingVersionData>
     */
    protected function getExistingVersions(Repository $repository): Collection
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
            ));
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
    protected function hasChanged(RefData $ref, Collection $existingVersions, Collection $emptyRefs, ?TagFormat $format): bool
    {
        // A tag and a branch can share a name but never a version, and versions a
        // ref was given under an earlier naming are gone, so only the version this
        // ref releases now counts
        $version = TagFormat::target($format, $ref)?->version;
        $existing = $existingVersions->get($format !== null ? $ref->name : (string) $version)
            ?->whereStrict('version', $version);

        if ($existing === null || $existing->isEmpty()) {
            return $emptyRefs->get($ref->name) !== $ref->commit;
        }

        return $existing->contains(
            fn (ExistingVersionData $existingVersion) => $existingVersion->distFailed
                || $existingVersion->sourceReference !== $ref->commit
        );
    }
}
