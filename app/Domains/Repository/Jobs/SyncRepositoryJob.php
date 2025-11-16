<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\CollectRefsAction;
use App\Domains\Repository\Actions\CompleteSyncLogAction;
use App\Domains\Repository\Actions\CreateSyncLogAction;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\SyncResultData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Exceptions\ComposerMetadataException;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\DataCollection;
use Throwable;

class SyncRepositoryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Repository $repository
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        CreateSyncLogAction $createSyncLogAction,
        CollectRefsAction $collectRefsAction,
        SyncRefAction $syncRefAction,
        CompleteSyncLogAction $completeSyncLogAction
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

            $result = $this->syncRefs($syncRefAction, $provider, $refs->all);

            $completeSyncLogAction->handle($syncLog, SyncStatus::Success, $result);

            $this->repository->update([
                'sync_status' => RepositorySyncStatus::Ok,
                'last_synced_at' => now(),
            ]);

            Log::info('Repository synced successfully', [
                'repository' => $this->repository->name,
                'versions_added' => $result->added,
                'versions_updated' => $result->updated,
            ]);
        } catch (Throwable $e) {
            $this->handleSyncFailure($syncLog, $e);

            throw $e;
        }
    }

    /**
     * Validate that the provider can access the repository.
     */
    protected function validateProvider(GitProviderInterface $provider): void
    {
        if (! $provider->validateCredentials()) {
            throw new GitProviderException('Failed to validate repository access');
        }
    }

    /**
     * Sync all refs (tags and branches).
     *
     * @param  DataCollection<int, RefData>  $refs
     */
    protected function syncRefs(
        SyncRefAction $syncRefAction,
        GitProviderInterface $provider,
        DataCollection $refs
    ): SyncResultData {
        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($refs as $ref) {
            try {
                $result = $syncRefAction->handle($provider, $this->repository, $ref);

                match ($result) {
                    'added' => $added++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                    default => throw new \UnexpectedValueException("Unexpected sync result: {$result}"),
                };
            } catch (ComposerMetadataException $e) {
                Log::warning('Skipping ref due to invalid composer.json', [
                    'repository' => $this->repository->name,
                    'ref' => $ref->name,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return new SyncResultData(
            added: $added,
            updated: $updated,
            skipped: $skipped,
        );
    }

    /**
     * Handle sync failure.
     */
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
