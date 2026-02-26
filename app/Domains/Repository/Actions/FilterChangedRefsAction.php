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
     * existing PackageVersion records to avoid unnecessary API calls.
     */
    public function handle(RefsCollectionData $refs, Repository $repository): RefsCollectionData
    {
        $existingVersions = $this->getExistingVersionLookup($repository);

        // If no packages exist yet, all refs are new
        if ($existingVersions->isEmpty()) {
            return $refs;
        }

        $filterChanged = fn (RefData $ref): bool => $this->hasChanged($ref, $existingVersions);

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
     * Build a keyed collection of existing package versions for fast lookup.
     *
     * @return Collection<string, ExistingVersionData>
     */
    protected function getExistingVersionLookup(Repository $repository): Collection
    {
        $packageUuids = $repository->packages()->pluck('uuid');

        if ($packageUuids->isEmpty()) {
            return collect();
        }

        return PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->whereNotNull('source_reference')
            ->get(['version', 'source_reference'])
            ->map(fn (PackageVersion $pv) => new ExistingVersionData(
                version: $pv->version,
                sourceReference: (string) $pv->source_reference,
            ))
            ->keyBy('version');
    }

    /**
     * Determine if a ref has changed compared to existing versions.
     *
     * @param  Collection<string, ExistingVersionData>  $existingVersions
     */
    protected function hasChanged(RefData $ref, Collection $existingVersions): bool
    {
        $version = ComposerMetadataData::extractVersion($ref->name);
        $existing = $existingVersions->get($version);

        return ! $existing || ! $existing->matches($version, $ref->commit);
    }
}
