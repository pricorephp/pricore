<?php

namespace App\Domains\Mirror\Actions;

use App\Models\Mirror;
use App\Models\Package;

class FindOrCreateMirrorPackageAction
{
    public function handle(Mirror $mirror, string $packageName): Package
    {
        return Package::query()
            ->firstOrCreate([
                'organization_uuid' => $mirror->organization_uuid,
                'name' => $packageName,
            ], [
                'mirror_uuid' => $mirror->uuid,
                'type' => 'library',
                'visibility' => 'private',
            ]);
    }
}
