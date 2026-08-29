<?php

namespace App\Domains\Repository\Actions;

use App\Models\DistArchive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PurgeDistArchiveFilesTask
{
    /**
     * Delete archive files for a set of packages before their rows disappear.
     *
     * Deleting a repository cascades packages, versions and archive rows at the
     * database level, which fires no model events. Without this the files are
     * stranded on disk with nothing left pointing at them.
     *
     * @param  Collection<int, string>  $packageUuids
     * @return int Number of files deleted
     */
    public function handle(Collection $packageUuids): int
    {
        if ($packageUuids->isEmpty()) {
            return 0;
        }

        $disk = Storage::disk(config('pricore.dist.disk'));
        $deleted = 0;

        DistArchive::query()
            ->whereIn('package_uuid', $packageUuids->all())
            ->lazyById(500, 'uuid')
            ->each(function (DistArchive $archive) use ($disk, &$deleted) {
                $disk->delete($archive->path);
                $deleted++;
            });

        return $deleted;
    }
}
