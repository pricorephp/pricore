<?php

namespace App\Domains\Mirror\Jobs;

use App\Domains\Mirror\Actions\CreateMirrorSyncLogAction;
use App\Domains\Mirror\Actions\RemoveStaleMirrorVersionsAction;
use App\Domains\Mirror\Events\MirrorSyncStatusUpdated;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Mirror;
use App\Models\MirrorSyncLog;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMirrorJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(
        public Mirror $mirror
    ) {}

    public function uniqueId(): string
    {
        return "sync-mirror-{$this->mirror->uuid}";
    }

    public function handle(
        CreateMirrorSyncLogAction $createMirrorSyncLogAction,
        RemoveStaleMirrorVersionsAction $removeStaleMirrorVersionsAction,
    ): void {
        $syncLog = $createMirrorSyncLogAction->handle($this->mirror);

        event(new MirrorSyncStatusUpdated(
            organizationUuid: $this->mirror->organization_uuid,
            mirrorUuid: $this->mirror->uuid,
            syncStatus: RepositorySyncStatus::Pending,
            lastSyncedAt: $this->mirror->last_synced_at?->toISOString(),
        ));

        try {
            $registryClient = RegistryClientFactory::make($this->mirror);

            $availablePackages = $registryClient->getAvailablePackages();

            if (empty($availablePackages)) {
                $this->completeSyncLogEmpty($syncLog);

                return;
            }

            // Collect all versions per package for stale removal and job dispatch
            $allPackageVersions = [];
            foreach ($availablePackages as $packageName) {
                $allPackageVersions[$packageName] = $registryClient->getPackageVersions($packageName);
            }

            $staleVersionsRemoved = $removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

            $totalVersions = array_sum(array_map('count', $allPackageVersions));

            $syncLog->update([
                'versions_removed' => $staleVersionsRemoved,
                'details' => [
                    'packages_found' => count($availablePackages),
                    'versions_found' => $totalVersions,
                    'stale_versions_removed' => $staleVersionsRemoved,
                ],
            ]);

            // Dispatch one lightweight job per version (like SyncRefJob for repos)
            $jobs = [];
            foreach ($allPackageVersions as $packageName => $versions) {
                foreach (array_keys($versions) as $version) {
                    $jobs[] = new SyncMirrorVersionJob(
                        $this->mirror,
                        $packageName,
                        (string) $version,
                    );
                }
            }

            $syncLogUuid = $syncLog->uuid;
            $mirrorUuid = $this->mirror->uuid;

            $batch = Bus::batch($jobs)
                ->name("sync-mirror:{$this->mirror->uuid}")
                ->allowFailures()
                ->finally(function (Batch $batch) use ($syncLogUuid, $mirrorUuid) {
                    CompleteMirrorSyncBatchJob::dispatchSync(
                        $syncLogUuid,
                        $mirrorUuid,
                        $batch->id,
                    );
                })
                ->dispatch();

            $syncLog->update(['batch_id' => $batch->id]);

            Log::info('Mirror sync batch dispatched', [
                'mirror' => $this->mirror->name,
                'batch_id' => $batch->id,
                'total_jobs' => count($jobs),
            ]);
        } catch (Throwable $e) {
            $this->handleSyncFailure($syncLog, $e);

            throw $e;
        }
    }

    protected function completeSyncLogEmpty(MirrorSyncLog $syncLog): void
    {
        $syncLog->update([
            'status' => SyncStatus::Success,
            'completed_at' => now(),
            'versions_added' => 0,
            'versions_updated' => 0,
            'versions_skipped' => 0,
            'versions_failed' => 0,
        ]);

        $this->mirror->update([
            'sync_status' => RepositorySyncStatus::Ok,
            'last_synced_at' => now(),
        ]);

        $this->mirror->refresh();

        event(new MirrorSyncStatusUpdated(
            organizationUuid: $this->mirror->organization_uuid,
            mirrorUuid: $this->mirror->uuid,
            syncStatus: RepositorySyncStatus::Ok,
            lastSyncedAt: $this->mirror->last_synced_at?->toISOString(),
        ));

        Log::info('Mirror sync completed (no packages)', [
            'mirror' => $this->mirror->name,
        ]);
    }

    protected function handleSyncFailure(MirrorSyncLog $syncLog, Throwable $e): void
    {
        $syncLog->update([
            'status' => SyncStatus::Failed,
            'completed_at' => now(),
            'error_message' => $e->getMessage(),
        ]);

        $this->mirror->update([
            'sync_status' => RepositorySyncStatus::Failed,
        ]);

        event(new MirrorSyncStatusUpdated(
            organizationUuid: $this->mirror->organization_uuid,
            mirrorUuid: $this->mirror->uuid,
            syncStatus: RepositorySyncStatus::Failed,
            lastSyncedAt: $this->mirror->last_synced_at?->toISOString(),
        ));

        Log::error('Mirror sync failed', [
            'mirror' => $this->mirror->name,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
