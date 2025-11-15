<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\SyncResultData;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\RepositorySyncLog;

class CompleteSyncLogAction
{
    /**
     * Complete the sync log with the given status and result.
     */
    public function handle(RepositorySyncLog $syncLog, SyncStatus $status, SyncResultData $result): void
    {
        $syncLog->update([
            'status' => $status,
            'completed_at' => now(),
            'versions_added' => $result->added,
            'versions_updated' => $result->updated,
            'details' => array_merge($syncLog->details ?? [], [
                'skipped' => $result->skipped,
            ]),
        ]);
    }
}
