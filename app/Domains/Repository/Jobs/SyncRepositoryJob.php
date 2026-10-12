<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\CollectRefsAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Actions\CreateSyncLogAction;
use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\PruneEmptyRefsAction;
use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Events\RepositorySyncStatusUpdated;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncRepositoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public int $uniqueFor = 300;

    /**
     * How long a batch counts as running. Its jobs give up after as long, so a
     * batch still unfinished by then was lost and must not block syncs forever.
     */
    public const RUNNING_BATCH_HOURS = 6;

    /**
     * Declared with a default rather than promoted, so jobs queued before this
     * property existed still unserialize with it set.
     */
    public bool $force = false;

    public function __construct(
        public Repository $repository,
        bool $force = false,
    ) {
        $this->force = $force;
    }

    public function uniqueId(): string
    {
        return "sync-repository-{$this->repository->uuid}";
    }

    public function handle(
        CreateSyncLogAction $createSyncLogAction,
        CollectRefsAction $collectRefsAction,
        CreateGitCloneAction $createGitCloneAction,
        FilterChangedRefsAction $filterChangedRefsAction,
        RemoveStaleVersionsAction $removeStaleVersionsAction,
        PruneEmptyRefsAction $pruneEmptyRefsAction,
    ): void {
        if ($this->deferWhileBatchRuns()) {
            return;
        }

        $syncLog = $createSyncLogAction->handle($this->repository);

        event(new RepositorySyncStatusUpdated(
            organizationUuid: $this->repository->organization_uuid,
            repositoryUuid: $this->repository->uuid,
            syncStatus: RepositorySyncStatus::Pending,
            lastSyncedAt: $this->repository->last_synced_at?->toISOString(),
        ));

        try {
            $provider = GitProviderFactory::make($this->repository);

            $this->validateProvider($provider);

            $refs = $collectRefsAction->handle($provider);

            // Read as late as possible: a request made while this job was queued
            // could not queue a job of its own, as this one holds the unique lock
            $this->repository->refresh();
            $fullSyncRequestedAt = $this->repository->full_sync_requested_at;
            $syncRequestedAt = $this->repository->sync_requested_at;

            $totalRefs = $refs->all->count();
            // A forced sync revisits every ref, e.g. after the package paths changed
            $filteredRefs = $this->force || $fullSyncRequestedAt !== null
                ? $refs
                : $filterChangedRefsAction->handle($refs, $this->repository);
            $skippedCount = $totalRefs - $filteredRefs->all->count();

            $staleVersionsRemoved = $removeStaleVersionsAction->handle($this->repository, $refs);
            $pruneEmptyRefsAction->handle($this->repository, $refs);

            $syncLog->update([
                'versions_removed' => $staleVersionsRemoved,
                'details' => [
                    'tags_found' => $refs->tags->count(),
                    'branches_found' => $refs->branches->count(),
                    'total_refs' => $totalRefs,
                    'refs_filtered' => $skippedCount,
                    'refs_to_sync' => $filteredRefs->all->count(),
                    'stale_versions_removed' => $staleVersionsRemoved,
                ],
            ]);

            if ($skippedCount > 0) {
                Log::info('Filtered unchanged refs', [
                    'repository' => $this->repository->name,
                    'total_refs' => $totalRefs,
                    'filtered' => $skippedCount,
                    'remaining' => $filteredRefs->all->count(),
                ]);
            }

            if ($filteredRefs->all->count() === 0) {
                $this->completeSyncLogEmpty($syncLog);
                $this->clearSyncRequests($fullSyncRequestedAt, $syncRequestedAt);

                return;
            }

            $clonePath = $createGitCloneAction->handle($this->repository);

            $jobs = collect($filteredRefs->all->toArray())->map(
                fn (array $refData) => new SyncRefJob(
                    $this->repository,
                    RefData::from($refData),
                    $clonePath,
                )
            )->all();

            // Capture only primitive values for the closure to avoid serialization issues
            $syncLogUuid = $syncLog->uuid;
            $repositoryUuid = $this->repository->uuid;

            $batch = Bus::batch($jobs)
                ->name("sync-repository:{$this->repository->uuid}")
                ->allowFailures()
                ->finally(function (Batch $batch) use ($syncLogUuid, $repositoryUuid, $clonePath) {
                    CompleteSyncBatchJob::dispatchSync(
                        $syncLogUuid,
                        $repositoryUuid,
                        $clonePath,
                        $batch->id,
                    );
                })
                ->dispatch();

            $syncLog->update(['batch_id' => $batch->id]);
            $this->clearSyncRequests($fullSyncRequestedAt, $syncRequestedAt);

            Log::info('Repository sync batch dispatched', [
                'repository' => $this->repository->name,
                'batch_id' => $batch->id,
                'total_jobs' => count($jobs),
            ]);
        } catch (Throwable $e) {
            $this->handleSyncFailure($syncLog, $e);

            throw $e;
        }
    }

    protected function validateProvider(GitProviderInterface $provider): void
    {
        if ($provider->validateCredentials()) {
            return;
        }

        throw new GitProviderException('Failed to validate repository access');
    }

    /**
     * The unique lock is released once the batch is dispatched, while its jobs
     * may run for a long time after. A sync started meanwhile would queue every
     * ref the batch has not reached yet a second time, so it is recorded as a
     * request instead, and the batch completion starts one sync for all of them.
     */
    protected function deferWhileBatchRuns(): bool
    {
        if (! $this->hasRunningBatch()) {
            return false;
        }

        $this->repository->update([
            $this->force ? 'full_sync_requested_at' : 'sync_requested_at' => now(),
        ]);

        // The batch may have completed before the request was recorded, too
        // late to see it; then this sync runs after all
        if (! $this->hasRunningBatch()) {
            return false;
        }

        Log::info('Repository sync deferred until the running batch completes', [
            'repository' => $this->repository->name,
            'force' => $this->force,
        ]);

        return true;
    }

    /**
     * @phpstan-impure
     */
    protected function hasRunningBatch(): bool
    {
        $batchId = $this->repository->syncLogs()
            ->where('status', SyncStatus::Pending)
            ->whereNotNull('batch_id')
            ->where('started_at', '>=', now()->subHours(self::RUNNING_BATCH_HOURS))
            ->latest('started_at')
            ->value('batch_id');

        if (! is_string($batchId)) {
            return false;
        }

        $batch = app(BatchRepository::class)->find($batchId);

        return $batch !== null && ! $batch->finished() && ! $batch->cancelled();
    }

    /**
     * Only the requests this run acted on are cleared; a newer one made while
     * it ran stays, and the batch completion starts another sync for it.
     */
    protected function clearSyncRequests(?CarbonInterface $fullSyncRequestedAt, ?CarbonInterface $syncRequestedAt): void
    {
        $requests = [
            'full_sync_requested_at' => $fullSyncRequestedAt,
            'sync_requested_at' => $syncRequestedAt,
        ];

        foreach ($requests as $column => $requestedAt) {
            if ($requestedAt === null) {
                continue;
            }

            Repository::query()
                ->whereKey($this->repository->uuid)
                ->where($column, '<=', $requestedAt)
                ->update([$column => null]);
        }
    }

    protected function completeSyncLogEmpty(RepositorySyncLog $syncLog): void
    {
        $syncLog->update([
            'status' => SyncStatus::Success,
            'completed_at' => now(),
            'versions_added' => 0,
            'versions_updated' => 0,
            'versions_skipped' => 0,
            'versions_failed' => 0,
        ]);

        $this->repository->update([
            'sync_status' => RepositorySyncStatus::Ok,
            'last_synced_at' => now(),
        ]);

        $this->repository->refresh();

        event(new RepositorySyncStatusUpdated(
            organizationUuid: $this->repository->organization_uuid,
            repositoryUuid: $this->repository->uuid,
            syncStatus: RepositorySyncStatus::Ok,
            lastSyncedAt: $this->repository->last_synced_at?->toISOString(),
        ));

        Log::info('Repository sync completed (no refs)', [
            'repository' => $this->repository->name,
        ]);
    }

    protected function handleSyncFailure(RepositorySyncLog $syncLog, Throwable $e): void
    {
        $syncLog->update([
            'status' => SyncStatus::Failed,
            'completed_at' => now(),
            'error_message' => $e->getMessage(),
        ]);

        $this->repository->update([
            'sync_status' => RepositorySyncStatus::Failed,
        ]);

        event(new RepositorySyncStatusUpdated(
            organizationUuid: $this->repository->organization_uuid,
            repositoryUuid: $this->repository->uuid,
            syncStatus: RepositorySyncStatus::Failed,
            lastSyncedAt: $this->repository->last_synced_at?->toISOString(),
        ));

        Log::error('Repository sync failed', [
            'repository' => $this->repository->name,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
