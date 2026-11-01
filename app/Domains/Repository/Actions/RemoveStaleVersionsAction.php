<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Contracts\Data\RefTargetData;
use App\Domains\Repository\Services\TagFormats\TagFormat;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RemoveStaleVersionsAction
{
    /**
     * Remove package versions that no longer exist in the repository's refs. With
     * a tag format, a package keeps the shared versions plus those of its own tags,
     * and a version its own tag created goes once that tag is deleted, even when a
     * shared tag has the same version; the sync releases it again from that tag.
     *
     * @return int Number of versions removed
     */
    public function handle(Repository $repository, RefsCollectionData $refs): int
    {
        $format = TagFormat::forRepository($repository);
        $targets = $this->targets($format, $refs);

        if ($targets->isEmpty()) {
            return 0;
        }

        $packageUuids = $repository->packages()->pluck('uuid');

        if ($packageUuids->isEmpty()) {
            return 0;
        }

        $sharedVersions = $targets->filter(fn (RefTargetData $target) => $target->isShared())
            ->pluck('version')
            ->unique()
            ->values()
            ->all();

        $versionsByPackageKey = $targets->reject(fn (RefTargetData $target) => $target->isShared())
            ->groupBy('packageKey')
            ->map(fn (Collection $packageTargets) => $packageTargets->pluck('version')->all());

        $tagNames = collect($refs->tags->toCollection())->pluck('name')->all();

        // Deleted one model at a time so the deleting hook removes each version's
        // archive files; a bulk delete fires no events and strands them.
        $deleted = 0;

        PackageVersion::query()
            ->with('package:uuid,name')
            ->whereIn('package_uuid', $packageUuids)
            ->when($format === null, fn ($query) => $query->whereNotIn('version', $sharedVersions))
            ->lazyById(100, 'uuid')
            ->filter(function (PackageVersion $version) use ($format, $sharedVersions, $versionsByPackageKey, $tagNames) {
                if ($format === null) {
                    return true;
                }

                if ($this->ownedByDeletedPackageTag($format, $version, $tagNames)) {
                    return true;
                }

                $packageKey = $format->keyFor($version->source_path, $version->package->name);

                return ! in_array($version->version, $sharedVersions, true)
                    && ($packageKey === null || ! in_array($version->version, $versionsByPackageKey->get($packageKey, []), true));
            })
            ->each(function (PackageVersion $version) use (&$deleted) {
                $version->delete();
                $deleted++;
            });

        if ($deleted > 0) {
            Log::info('Removed stale package versions', [
                'repository' => $repository->name,
                'versions_removed' => $deleted,
            ]);
        }

        return $deleted;
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    protected function ownedByDeletedPackageTag(TagFormat $format, PackageVersion $version, array $tagNames): bool
    {
        if ($version->source_tag === null || in_array($version->source_tag, $tagNames, true)) {
            return false;
        }

        $owner = TagFormat::target($format, new RefData($version->source_tag, '', isTag: true));

        return $owner !== null && ! $owner->isShared();
    }

    /**
     * @return Collection<int, RefTargetData>
     */
    protected function targets(?TagFormat $format, RefsCollectionData $refs): Collection
    {
        $tags = collect($refs->tags->toCollection())
            ->map(fn (RefData $ref) => TagFormat::target($format, new RefData($ref->name, $ref->commit, isTag: true)));

        $branches = collect($refs->branches->toCollection())
            ->map(fn (RefData $ref) => TagFormat::target($format, new RefData($ref->name, $ref->commit)));

        return $tags->merge($branches)->filter()->values();
    }
}
