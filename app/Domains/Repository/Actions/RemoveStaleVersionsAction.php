<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class RemoveStaleVersionsAction
{
    /**
     * Remove package versions that no longer exist in the repository's refs.
     *
     * @return int Number of versions removed
     */
    public function handle(Repository $repository, RefsCollectionData $refs): int
    {
        $currentVersions = collect($refs->all->toArray())
            ->map(fn (array $ref) => ComposerMetadataData::extractVersion($ref['name']))
            ->unique()
            ->values()
            ->all();

        if (empty($currentVersions)) {
            return 0;
        }

        $packageUuids = $repository->packages()->pluck('uuid');

        if ($packageUuids->isEmpty()) {
            return 0;
        }

        $deleted = PackageVersion::query()
            ->whereIn('package_uuid', $packageUuids)
            ->whereNotIn('version', $currentVersions)
            ->delete();

        if ($deleted > 0) {
            Log::info('Removed stale package versions', [
                'repository' => $repository->name,
                'versions_removed' => $deleted,
            ]);
        }

        return $deleted;
    }
}
