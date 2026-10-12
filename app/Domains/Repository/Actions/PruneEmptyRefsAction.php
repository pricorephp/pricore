<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\Repository;

class PruneEmptyRefsAction
{
    /**
     * Forget empty refs that no longer exist in the repository, such as
     * deleted branches, so the records do not pile up.
     */
    public function handle(Repository $repository, RefsCollectionData $refs): void
    {
        // No refs at all more likely means a failed listing than an empty repository
        if ($refs->all->count() === 0) {
            return;
        }

        $current = collect($refs->all->toArray())->pluck('name')->flip();

        $removed = $repository->emptyRefs()
            ->pluck('name', 'uuid')
            ->reject(fn (string $name) => $current->has($name))
            ->keys();

        foreach ($removed->chunk(500) as $uuids) {
            $repository->emptyRefs()->whereKey($uuids->all())->delete();
        }
    }
}
