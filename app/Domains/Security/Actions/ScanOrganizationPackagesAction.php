<?php

namespace App\Domains\Security\Actions;

use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Organization;

class ScanOrganizationPackagesAction
{
    public function handle(Organization $organization): void
    {
        $organization->packages->each(function ($package) {
            ScanPackageVersionsJob::dispatch($package);
        });
    }
}
