<?php

namespace App\Domains\Repository\Jobs;

use App\Domains\Repository\Actions\CleanupGitCloneAction;
use App\Domains\Repository\Actions\CreateDistArchiveAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\CachedGitProvider;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\DistArchive;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds again an archive whose file is gone, for a lock file that pins its
 * commit. A failure is recorded instead of retried, so downloads fall back
 * to source until the next request after the failure window.
 */
class RebuildDistArchiveJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Downloads asking meanwhile get a 503 instead of queueing another build */
    public int $uniqueFor = 600;

    public function __construct(
        public DistArchive $archive,
    ) {}

    public function uniqueId(): string
    {
        return $this->archive->uuid;
    }

    public function handle(
        CreateDistArchiveAction $createDistArchiveAction,
        CreateGitCloneAction $createGitCloneAction,
        CleanupGitCloneAction $cleanupGitCloneAction,
    ): void {
        $archive = $this->archive->loadMissing(['package.repository', 'package.organization', 'packageVersion']);
        $package = $archive->package;
        $version = $archive->packageVersion;
        $repository = $package->repository;
        $clonePath = null;

        try {
            if (! $repository || ! $package->organization) {
                throw new \RuntimeException('The package is no longer synced from a repository');
            }

            // Its own clone: the sync's one is deleted once a sync completes
            if ($repository->provider === GitProvider::Git) {
                $clonePath = $createGitCloneAction->handle($repository, storage_path('app/git-clones/rebuild-'.Str::uuid()));
            }

            $dist = $createDistArchiveAction->handle(
                provider: $clonePath ? new CachedGitProvider($clonePath, $repository->repo_identifier) : GitProviderFactory::make($repository),
                organizationSlug: $package->organization->slug,
                packageName: $package->name,
                version: $version->version,
                reference: $archive->source_reference,
                sourcePath: $archive->source_path,
            );

            if ($dist === null) {
                throw new \RuntimeException('The provider returned no archive');
            }

            $this->record($archive, $dist, $repository);
        } catch (Throwable $e) {
            Log::warning('Failed to rebuild dist archive', [
                'package' => $package->name,
                'version' => $version->version,
                'reference' => $archive->source_reference,
                'error' => $e->getMessage(),
            ]);

            $archive->update(['rebuild_failed_at' => now()]);

            return;
        } finally {
            $cleanupGitCloneAction->handle($clonePath);
        }
    }

    protected function record(DistArchive $archive, DistArchiveData $dist, Repository $repository): void
    {
        // Lock files keep the old checksum and fall back to source; the new one
        // still serves every lock resolved from now on.
        if ($archive->shasum !== null && $archive->shasum !== $dist->shasum) {
            Log::warning('Rebuilt dist archive differs from the original', [
                'repository' => $repository->name,
                'package' => $archive->package->name,
                'reference' => $archive->source_reference,
                'previous_shasum' => $archive->shasum,
                'shasum' => $dist->shasum,
            ]);
        }

        DB::transaction(function () use ($archive, $dist) {
            // Version first, then archive: the order a sync takes its locks in,
            // so the two never wait on each other
            $version = PackageVersion::query()
                ->whereKey($archive->package_version_uuid)
                ->lockForUpdate()
                ->first();

            $archive->update([
                'path' => $dist->path,
                'shasum' => $dist->shasum,
                'size' => $dist->size,
                'file_removed_at' => null,
                'rebuild_failed_at' => null,
            ]);

            // A sync may have moved the version to another commit while the
            // archive was building
            if ($version?->source_reference === $archive->source_reference) {
                $version->update([
                    'dist_path' => $dist->path,
                    'dist_shasum' => $dist->shasum,
                    'dist_size' => $dist->size,
                ]);
            }
        });
    }
}
