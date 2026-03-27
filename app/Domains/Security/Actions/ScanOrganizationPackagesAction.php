<?php

namespace App\Domains\Security\Actions;

use App\Domains\Security\Events\AdvisorySyncCompleted;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Organization;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class ScanOrganizationPackagesAction
{
    public function handle(Organization $organization): void
    {
        $jobs = $organization->packages
            ->map(fn ($package) => new ScanPackageVersionsJob($package))
            ->all();

        if (empty($jobs)) {
            event(new AdvisorySyncCompleted($organization->uuid));

            return;
        }

        Bus::batch($jobs)
            ->finally(function (Batch $batch) use ($organization) {
                event(new AdvisorySyncCompleted($organization->uuid));
            })
            ->dispatch();
    }
}
