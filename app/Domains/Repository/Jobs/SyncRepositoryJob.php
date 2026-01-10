<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\CollectRefsAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Actions\CreateSyncLogAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Bus\Batch;
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

    public function __construct(
        public Repository $repository
    ) {}

    public function uniqueId(): string
    {
        return "sync-repository-{$this->repository->uuid}";
    }

    public function handle(
        CreateSyncLogAction $createSyncLogAction,
        CollectRefsAction $collectRefsAction,
        CreateGitCloneAction $createGitCloneAction,
    ): void {
        $syncLog = $createSyncLogAction->handle($this->repository);

        try {
            $provider = GitProviderFactory::make($this->repository);

            $this->validateProvider($provider);

            $refs = $collectRefsAction->handle($provider);

            $syncLog->update([
                'details' => [
                    'tags_found' => $refs->tags->count(),
                    'branches_found' => $refs->branches->count(),
                    'total_refs' => $refs->all->count(),
                ],
            ]);

            if ($refs->all->count() === 0) {
                $this->completeSyncLogEmpty($syncLog);

                return;
            }

            $clonePath = $createGitCloneAction->handle($this->repository);

            $jobs = collect($refs->all->toArray())->map(
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
        if (! $provider->validateCredentials()) {
            throw new GitProviderException('Failed to validate repository access');
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

        Log::error('Repository sync failed', [
            'repository' => $this->repository->name,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
