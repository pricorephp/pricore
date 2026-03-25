<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Actions\CleanupGitCloneAction;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Domains\Repository\Events\RepositorySyncStatusUpdated;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
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

    public function handle(CleanupGitCloneAction $cleanupGitCloneAction, RecordActivityTask $recordActivityTask): void
    {
        $syncLog = RepositorySyncLog::findOrFail($this->syncLogUuid);
        $repository = Repository::findOrFail($this->repositoryUuid);
        $batch = $this->getBatch();

        $this->completeSyncLog($syncLog, $batch);
        $cleanupGitCloneAction->handle($this->clonePath);
        $this->updateRepositoryStatus($repository, $batch);
        $this->recordActivity($repository, $syncLog, $recordActivityTask);
        $this->scanForVulnerabilities($repository, $syncLog);
    }

    protected function getBatch(): ?Batch
    {
        return app(BatchRepository::class)->find($this->batchId);
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
        $failedJobs = $batch->failedJobs ?? 0;

        $status = $failedJobs > 0
            ? RepositorySyncStatus::Failed
            : RepositorySyncStatus::Ok;

        $repository->update([
            'sync_status' => $status,
            'last_synced_at' => now(),
        ]);

        $repository->refresh();

        event(new RepositorySyncStatusUpdated(
            organizationUuid: $repository->organization_uuid,
            repositoryUuid: $repository->uuid,
            syncStatus: $status,
            lastSyncedAt: $repository->last_synced_at?->toISOString(),
        ));
    }

    protected function recordActivity(Repository $repository, RepositorySyncLog $syncLog, RecordActivityTask $recordActivityTask): void
    {
        $hasChanges = $syncLog->versions_added > 0
            || $syncLog->versions_updated > 0
            || $syncLog->versions_removed > 0;

        if ($syncLog->status->isFailed()) {
            $recordActivityTask->handle(
                organization: $repository->organization,
                type: ActivityType::RepositorySyncFailed,
                subject: $repository,
                properties: [
                    'name' => $repository->name,
                    'error_message' => $syncLog->error_message,
                    'sync_log_uuid' => $syncLog->uuid,
                ],
            );

            return;
        }

        if (! $hasChanges) {
            return;
        }

        $recordActivityTask->handle(
            organization: $repository->organization,
            type: ActivityType::RepositorySynced,
            subject: $repository,
            properties: [
                'name' => $repository->name,
                'versions_added' => $syncLog->versions_added,
                'versions_updated' => $syncLog->versions_updated,
                'versions_removed' => $syncLog->versions_removed,
                'sync_log_uuid' => $syncLog->uuid,
            ],
        );
    }

    protected function scanForVulnerabilities(Repository $repository, RepositorySyncLog $syncLog): void
    {
        $hasChanges = $syncLog->versions_added > 0 || $syncLog->versions_updated > 0;

        if (! $hasChanges) {
            return;
        }

        $repository->packages->each(function ($package) {
            ScanPackageVersionsJob::dispatch($package);
        });
    }
}
