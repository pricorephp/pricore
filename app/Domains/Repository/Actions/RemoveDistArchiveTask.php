<?php

namespace App\Domains\Repository\Actions;

use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

class RemoveDistArchiveTask
{
    /**
     * Drop the files of every archive a version owns. A package synced from a
     * repository keeps the rows and the version's pointers, so the metadata keeps
     * offering the dist and the first download builds it again. Any other package
     * loses rows and pointers alike.
     */
    public function handle(PackageVersion $version): void
    {
        $disk = Storage::disk(config('pricore.dist.disk'));

        if ($version->package->hasRebuildableDists()) {
            foreach ($version->archives()->whereNull('file_removed_at')->get() as $archive) {
                $disk->delete($archive->path);
                $archive->update(['file_removed_at' => now()]);
            }

            return;
        }

        foreach ($version->archives()->get() as $archive) {
            $disk->delete($archive->path);
            $archive->delete();
        }

        if ($version->dist_path) {
            $disk->delete($version->dist_path);
        }

        $version->update([
            'dist_url' => null,
            'dist_path' => null,
            'dist_shasum' => null,
            'dist_size' => null,
        ]);
    }
}
