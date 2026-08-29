<?php

namespace App\Domains\Repository\Actions;

use App\Models\DistArchive;
use App\Models\PackageVersion;

class DetachDistArchivesTask
{
    /**
     * Release a version from its current archive.
     *
     * Must run in the same transaction as any change to source_reference:
     * leaving the dist_* columns describing the previous commit would advertise
     * the old archive, and its matching shasum, under the new reference.
     */
    public function handle(PackageVersion $version): void
    {
        DistArchive::query()
            ->where('package_version_uuid', $version->uuid)
            ->whereNull('detached_at')
            ->update(['detached_at' => now()]);

        $version->update([
            'dist_url' => null,
            'dist_path' => null,
            'dist_shasum' => null,
            'dist_size' => null,
        ]);
    }
}
