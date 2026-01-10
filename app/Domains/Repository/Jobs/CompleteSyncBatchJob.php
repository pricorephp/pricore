<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\CleanupGitCloneAction;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompleteSyncBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $syncLogUuid,
        public string $repositoryUuid,
        public ?string $clonePath,
        public string $batchId,
    ) {}

    public function handle(CleanupGitCloneAction $cleanupGitCloneAction): void
    {
        $syncLog = RepositorySyncLog::findOrFail($this->syncLogUuid);
        $repository = Repository::findOrFail($this->repositoryUuid);
        $batch = $this->getBatch();

        $this->completeSyncLog($syncLog, $batch);
        $cleanupGitCloneAction->handle($this->clonePath);
        $this->updateRepositoryStatus($repository, $batch);
    }

    protected function getBatch(): ?Batch
    {
        return app(\Illuminate\Bus\BatchRepository::class)->find($this->batchId);
    }

    protected function completeSyncLog(RepositorySyncLog $syncLog, ?Batch $batch): void
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        if ($batch) {
            $added = (int) Cache::pull("sync-batch:{$batch->id}:added", 0);
            $updated = (int) Cache::pull("sync-batch:{$batch->id}:updated", 0);
            $skipped = (int) Cache::pull("sync-batch:{$batch->id}:skipped", 0);
            $failed = $batch->failedJobs;
        }

        $failedJobs = $batch ? $batch->failedJobs : 0;
        $totalJobs = $batch ? $batch->totalJobs : 0;

        $status = $failedJobs > 0 && $added === 0 && $updated === 0
            ? SyncStatus::Failed
            : SyncStatus::Success;

        $syncLog->update([
            'status' => $status,
            'completed_at' => now(),
            'versions_added' => $added,
            'versions_updated' => $updated,
            'versions_skipped' => $skipped,
            'versions_failed' => $failed,
            'details' => array_merge($syncLog->details ?? [], [
                'failed_jobs' => $failedJobs,
                'total_jobs' => $totalJobs,
            ]),
        ]);

        Log::info('Repository sync completed', [
            'repository_uuid' => $this->repositoryUuid,
            'versions_added' => $added,
            'versions_updated' => $updated,
            'versions_skipped' => $skipped,
            'versions_failed' => $failed,
        ]);
    }

    protected function updateRepositoryStatus(Repository $repository, ?Batch $batch): void
    {
        $failedJobs = $batch ? $batch->failedJobs : 0;

        $status = $failedJobs > 0
            ? RepositorySyncStatus::Failed
            : RepositorySyncStatus::Ok;

        $repository->update([
            'sync_status' => $status,
            'last_synced_at' => now(),
        ]);
    }
}
