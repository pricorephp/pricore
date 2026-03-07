<?php

namespace App\Domains\Repository\Actions;

use App\Models\Package;
use App\Models\Repository;

class FindOrCreatePackageAction
{
    /**
     * Find or create a package.
     */
    public function handle(Repository $repository, string $packageName): Package
    {
        return Package::query()
            ->firstOrCreate([
                'organization_uuid' => $repository->organization_uuid,
                'name' => $packageName,
            ], [
                'repository_uuid' => $repository->uuid,
                'type' => 'library',
                'visibility' => 'private',
            ]);
    }
}
