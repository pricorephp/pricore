<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Repository;
use App\Models\RepositorySyncLog;

class CreateSyncLogAction
{
    public function handle(Repository $repository): RepositorySyncLog
    {
        return RepositorySyncLog::create([
            'repository_uuid' => $repository->uuid,
            'status' => SyncStatus::Pending,
            'started_at' => now(),
        ]);
    }
}
