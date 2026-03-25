<?php

namespace App\Domains\Security\Jobs;

use App\Domains\Security\Actions\FetchAdvisoriesAction;
use App\Domains\Security\Exceptions\AdvisorySyncException;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAdvisoriesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'sync-advisories';
    }

    public function handle(FetchAdvisoriesAction $fetchAdvisoriesAction): void
    {
        try {
            $syncResultData = $fetchAdvisoriesAction->handle();

            if (! $syncResultData->hasChanges()) {
                Log::info('Advisory sync completed with no changes');

                return;
            }

            Log::info('Advisory sync completed, scanning packages', [
                'advisories_added' => $syncResultData->advisoriesAdded,
                'advisories_updated' => $syncResultData->advisoriesUpdated,
            ]);

            // Scan all organizations' packages for new advisory matches
            Organization::each(function (Organization $organization) {
                $organization->packages->each(function ($package) {
                    ScanPackageVersionsJob::dispatch($package);
                });
            });
        } catch (Throwable $e) {
            Log::error('Advisory sync failed', [
                'error' => $e->getMessage(),
            ]);

            throw new AdvisorySyncException(
                "Advisory sync failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
