<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Repository;

class RequestRepositorySyncAction
{
    public function handle(Repository $repository): void
    {
        $repository->update(['sync_status' => RepositorySyncStatus::Pending]);

        SyncRepositoryJob::dispatch($repository);
    }
}
