<?php

namespace App\Domains\Repository\Actions;

use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

class RemoveDistArchiveTask
{
    /**
     * Drop every archive a version owns, files and rows alike, and clear the
     * pointer columns.
     */
    public function handle(PackageVersion $version): void
    {
        $disk = Storage::disk(config('pricore.dist.disk'));

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
