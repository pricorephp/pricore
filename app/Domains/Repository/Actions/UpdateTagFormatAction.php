<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Repository;

class UpdateTagFormatAction
{
    /**
     * Store a new tag format and resync every ref, so versions follow it. Versions
     * the old format created that no longer fit are removed by that sync.
     */
    public function handle(Repository $repository, ?string $tagFormat): bool
    {
        $tagFormat = $tagFormat === '' ? null : $tagFormat;

        if ($tagFormat === $repository->tag_format) {
            return false;
        }

        // The forced sync must happen even when this dispatch is dropped because
        // a sync is already running; the next sync honours this.
        $repository->update([
            'tag_format' => $tagFormat,
            'full_sync_requested_at' => now(),
            'sync_status' => RepositorySyncStatus::Pending,
        ]);

        SyncRepositoryJob::dispatch($repository, true);

        return true;
    }
}
