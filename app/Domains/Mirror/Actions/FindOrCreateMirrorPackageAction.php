<?php

namespace App\Domains\Mirror\Actions;

use App\Models\Mirror;
use App\Models\Package;

class FindOrCreateMirrorPackageAction
{
    /**
     * Returns null when the name belongs to a package published from uploaded
     * archives: mirroring into it would mix upstream releases with the uploads.
     */
    public function handle(Mirror $mirror, string $packageName): ?Package
    {
        $package = Package::query()
            ->firstOrCreate([
                'organization_uuid' => $mirror->organization_uuid,
                'name' => $packageName,
            ], [
                'mirror_uuid' => $mirror->uuid,
                'type' => 'library',
                'visibility' => 'private',
            ]);

        return $package->is_artifact ? null : $package;
    }
}
