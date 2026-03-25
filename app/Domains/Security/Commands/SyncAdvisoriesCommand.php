<?php

namespace App\Domains\Security\Commands;

use App\Domains\Security\Actions\FetchAdvisoriesAction;
use App\Domains\Security\Jobs\SyncAdvisoriesJob;
use Illuminate\Console\Command;

class SyncAdvisoriesCommand extends Command
{
    protected $signature = 'security:sync-advisories {--sync : Run synchronously instead of dispatching a job}';

    protected $description = 'Sync security advisories from Packagist';

    public function handle(FetchAdvisoriesAction $fetchAdvisoriesAction): int
    {
        if ($this->option('sync')) {
            $this->info('Syncing advisories synchronously...');

            $syncResultData = $fetchAdvisoriesAction->handle();

            $this->info("Done. Added: {$syncResultData->advisoriesAdded}, Updated: {$syncResultData->advisoriesUpdated}");

            return self::SUCCESS;
        }

        SyncAdvisoriesJob::dispatch();

        $this->info('Advisory sync job dispatched.');

        return self::SUCCESS;
    }
}
