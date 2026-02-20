<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Services\GitProviders\CachedGitProvider;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Exceptions\ComposerMetadataException;
use App\Models\Repository;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncRefJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 60];

    public function __construct(
        public Repository $repository,
        public RefData $ref,
        public ?string $clonePath = null,
    ) {}

    public function handle(SyncRefAction $syncRefAction): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $provider = $this->createProvider();

        try {
            $result = $syncRefAction->handle($provider, $this->repository, $this->ref);

            $this->incrementCounter($result);
        } catch (ComposerMetadataException $e) {
            Log::warning('Skipping ref due to invalid composer.json', [
                'repository' => $this->repository->name,
                'ref' => $this->ref->name,
                'error' => $e->getMessage(),
            ]);

            $this->incrementCounter('skipped');
        } catch (\Throwable $e) {
            Log::error('Failed to sync ref', [
                'repository' => $this->repository->name,
                'ref' => $this->ref->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function createProvider(): GitProviderInterface
    {
        if ($this->clonePath && $this->repository->provider === GitProvider::Git) {
            return new CachedGitProvider(
                $this->clonePath,
                $this->repository->repo_identifier,
            );
        }

        return GitProviderFactory::make($this->repository);
    }

    protected function incrementCounter(string $result): void
    {
        $batch = $this->batch();

        if (! $batch) {
            return;
        }

        Cache::increment("sync-batch:{$batch->id}:{$result}");
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SyncRefJob failed permanently', [
            'repository' => $this->repository->name ?? 'unknown',
            'repository_uuid' => $this->repository->uuid ?? 'unknown',
            'ref' => $this->ref->name ?? 'unknown',
            'clone_path' => $this->clonePath,
            'error' => $exception?->getMessage() ?? 'No exception provided',
            'exception_class' => $exception ? get_class($exception) : 'none',
            'trace' => $exception?->getTraceAsString(),
        ]);

        $this->incrementCounter('failed');
    }
}
