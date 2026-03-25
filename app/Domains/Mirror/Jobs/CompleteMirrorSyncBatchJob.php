<?php

namespace App\Domains\Mirror\Jobs;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Mirror\Events\MirrorSyncStatusUpdated;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Mirror;
use App\Models\MirrorSyncLog;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompleteMirrorSyncBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $syncLogUuid,
        public string $mirrorUuid,
        public string $batchId,
    ) {}

    public function handle(RecordActivityTask $recordActivityTask): void
    {
        $syncLog = MirrorSyncLog::findOrFail($this->syncLogUuid);
        $mirror = Mirror::findOrFail($this->mirrorUuid);
        $batch = $this->getBatch();

        $this->completeSyncLog($syncLog, $batch);
        $this->updateMirrorStatus($mirror, $batch);
        $this->recordActivity($mirror, $syncLog, $recordActivityTask);
        $this->scanForVulnerabilities($mirror, $syncLog);
    }

    protected function getBatch(): ?Batch
    {
        return app(BatchRepository::class)->find($this->batchId);
    }

    protected function completeSyncLog(MirrorSyncLog $syncLog, ?Batch $batch): void
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $distFailed = 0;
        $distError = null;

        if ($batch) {
            $added = (int) Cache::pull("sync-batch:{$batch->id}:added", 0);
            $updated = (int) Cache::pull("sync-batch:{$batch->id}:updated", 0);
            $skipped = (int) Cache::pull("sync-batch:{$batch->id}:skipped", 0);
            $failed = (int) Cache::pull("sync-batch:{$batch->id}:failed", 0);
            $distFailed = (int) Cache::pull("sync-batch:{$batch->id}:dist_failed", 0);
            $distError = Cache::pull("sync-batch:{$batch->id}:dist_error");
        }

        $failedJobs = $batch ? $batch->failedJobs : 0;
        $totalJobs = $batch ? $batch->totalJobs : 0;

        $status = $failedJobs > 0 && $added === 0 && $updated === 0
            ? SyncStatus::Failed
            : SyncStatus::Success;

        $errorMessage = $distError && $distFailed > 0
            ? $distError
            : $syncLog->error_message;

        $syncLog->update([
            'status' => $status,
            'completed_at' => now(),
            'versions_added' => $added,
            'versions_updated' => $updated,
            'versions_skipped' => $skipped,
            'versions_failed' => $failed,
            'error_message' => $errorMessage,
            'details' => array_merge($syncLog->details ?? [], [
                'failed_jobs' => $failedJobs,
                'total_jobs' => $totalJobs,
                'dist_failed' => $distFailed,
            ]),
        ]);

        Log::info('Mirror sync completed', [
            'mirror_uuid' => $this->mirrorUuid,
            'versions_added' => $added,
            'versions_updated' => $updated,
            'versions_skipped' => $skipped,
            'versions_failed' => $failed,
        ]);
    }

    protected function updateMirrorStatus(Mirror $mirror, ?Batch $batch): void
    {
        $failedJobs = $batch->failedJobs ?? 0;

        $status = $failedJobs > 0
            ? RepositorySyncStatus::Failed
            : RepositorySyncStatus::Ok;

        $mirror->update([
            'sync_status' => $status,
            'last_synced_at' => now(),
        ]);

        $mirror->refresh();

        event(new MirrorSyncStatusUpdated(
            organizationUuid: $mirror->organization_uuid,
            mirrorUuid: $mirror->uuid,
            syncStatus: $status,
            lastSyncedAt: $mirror->last_synced_at?->toISOString(),
        ));
    }

    protected function recordActivity(Mirror $mirror, MirrorSyncLog $syncLog, RecordActivityTask $recordActivityTask): void
    {
        $hasChanges = $syncLog->versions_added > 0
            || $syncLog->versions_updated > 0
            || $syncLog->versions_removed > 0;

        if ($syncLog->status->isFailed()) {
            $recordActivityTask->handle(
                organization: $mirror->organization,
                type: ActivityType::MirrorSyncFailed,
                subject: $mirror,
                properties: [
                    'name' => $mirror->name,
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
            organization: $mirror->organization,
            type: ActivityType::MirrorSynced,
            subject: $mirror,
            properties: [
                'name' => $mirror->name,
                'versions_added' => $syncLog->versions_added,
                'versions_updated' => $syncLog->versions_updated,
                'versions_removed' => $syncLog->versions_removed,
                'sync_log_uuid' => $syncLog->uuid,
            ],
        );
    }

    protected function scanForVulnerabilities(Mirror $mirror, MirrorSyncLog $syncLog): void
    {
        $hasChanges = $syncLog->versions_added > 0 || $syncLog->versions_updated > 0;

        if (! $hasChanges) {
            return;
        }

        $mirror->packages->each(function ($package) {
            ScanPackageVersionsJob::dispatch($package);
        });
    }
}
