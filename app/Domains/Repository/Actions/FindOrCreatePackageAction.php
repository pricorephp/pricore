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
            ->where('organization_uuid', $repository->organization_uuid)
            ->where('name', $packageName)
            ->firstOr(function () use ($repository, $packageName): Package {
                return Package::create([
                    'organization_uuid' => $repository->organization_uuid,
                    'repository_uuid' => $repository->uuid,
                    'name' => $packageName,
                    'type' => 'library',
                    'visibility' => 'private',
                ]);
            });
    }
}
