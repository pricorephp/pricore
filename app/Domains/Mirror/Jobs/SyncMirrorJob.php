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
use App\Models\Package;
use App\Models\PackageVersion;
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

    public int $uniqueFor = 30;

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

            // Filter to only new/changed versions or versions missing dist archives
            $mirrorDist = $this->mirror->mirror_dist && config('pricore.dist.enabled');
            $jobs = [];
            $skippedCount = 0;

            foreach ($allPackageVersions as $packageName => $versions) {
                $existingVersions = $this->getExistingVersions($packageName, $mirrorDist);

                foreach ($versions as $version => $composerJson) {
                    $version = (string) $version;
                    $reference = $this->extractReference($composerJson);
                    $existing = $existingVersions[$version] ?? null;

                    if ($existing && $existing['reference'] === $reference && (! $mirrorDist || $existing['has_dist'])) {
                        $skippedCount++;

                        continue;
                    }

                    $jobs[] = new SyncMirrorVersionJob(
                        $this->mirror,
                        $packageName,
                        $version,
                    );
                }
            }

            if ($skippedCount > 0) {
                $syncLog->update([
                    'versions_skipped' => $skippedCount,
                    'details' => array_merge($syncLog->details ?? [], [
                        'versions_skipped_unchanged' => $skippedCount,
                    ]),
                ]);
            }

            if (empty($jobs)) {
                $this->completeSyncLogEmpty($syncLog);

                return;
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

    /**
     * Get existing version references for a package to filter unchanged versions.
     *
     * @return array<string, array{reference: string|null, has_dist: bool}>
     */
    protected function getExistingVersions(string $packageName, bool $checkDist): array
    {
        $package = Package::query()
            ->where('organization_uuid', $this->mirror->organization_uuid)
            ->where('name', $packageName)
            ->first();

        if (! $package) {
            return [];
        }

        return PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->get(['version', 'source_reference', 'dist_path'])
            ->keyBy('version')
            ->map(fn (PackageVersion $v) => [
                'reference' => $v->source_reference,
                'has_dist' => $v->dist_path !== null,
            ])
            ->all();
    }

    /**
     * Extract a reference from upstream composer.json metadata.
     *
     * @param  array<string, mixed>  $composerJson
     */
    protected function extractReference(array $composerJson): string
    {
        if (isset($composerJson['dist']['reference'])) {
            return (string) $composerJson['dist']['reference'];
        }

        if (isset($composerJson['source']['reference'])) {
            return (string) $composerJson['source']['reference'];
        }

        return hash('sha256', (string) json_encode($composerJson));
    }
}
