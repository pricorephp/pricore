<?php

namespace App\Domains\Repository\Actions;

use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

class RemoveDistArchiveTask
{
    public function handle(PackageVersion $version): void
    {
        if ($version->dist_path) {
            Storage::disk(config('pricore.dist.disk'))->delete($version->dist_path);
        }

        $version->update([
            'dist_url' => null,
            'dist_path' => null,
            'dist_shasum' => null,
            'dist_size' => null,
        ]);
    }
}
