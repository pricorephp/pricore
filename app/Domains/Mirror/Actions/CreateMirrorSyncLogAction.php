<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Mirror;
use App\Models\MirrorSyncLog;

class CreateMirrorSyncLogAction
{
    public function handle(Mirror $mirror): MirrorSyncLog
    {
        return MirrorSyncLog::create([
            'mirror_uuid' => $mirror->uuid,
            'status' => SyncStatus::Pending,
            'started_at' => now(),
        ]);
    }
}
