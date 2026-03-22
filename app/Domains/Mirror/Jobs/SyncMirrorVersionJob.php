<?php

namespace App\Domains\Mirror\Jobs;

use App\Domains\Mirror\Actions\DownloadMirrorDistAction;
use App\Domains\Mirror\Actions\FindOrCreateMirrorPackageAction;
use App\Domains\Mirror\Actions\SyncMirrorPackageVersionAction;
use App\Domains\Mirror\Exceptions\MirrorDistDownloadException;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Models\Mirror;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMirrorVersionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 60];

    public function __construct(
        public Mirror $mirror,
        public string $packageName,
        public string $version,
    ) {}

    public function handle(
        FindOrCreateMirrorPackageAction $findOrCreateMirrorPackageAction,
        SyncMirrorPackageVersionAction $syncMirrorPackageVersionAction,
        DownloadMirrorDistAction $downloadMirrorDistAction,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $registryClient = RegistryClientFactory::make($this->mirror);
        $versions = $registryClient->getPackageVersions($this->packageName);
        $composerJson = $versions[$this->version] ?? null;

        if (! $composerJson) {
            $this->incrementCounter('skipped');

            return;
        }

        $package = $findOrCreateMirrorPackageAction->handle($this->mirror, $this->packageName);

        $result = $syncMirrorPackageVersionAction->handle(
            $package,
            $this->version,
            $composerJson,
        );

        $this->incrementCounter($result);

        if ($this->mirror->mirror_dist && config('pricore.dist.enabled')) {
            $this->mirrorDist($downloadMirrorDistAction, $package);
        }
    }

    protected function mirrorDist(
        DownloadMirrorDistAction $downloadMirrorDistAction,
        Package $package,
    ): void {
        $packageVersion = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->where('version', $this->version)
            ->first();

        if (! $packageVersion || $packageVersion->dist_path) {
            return;
        }

        try {
            $organizationSlug = $this->mirror->organization->slug;

            $dist = $downloadMirrorDistAction->handle(
                $this->mirror,
                $packageVersion,
                $package,
                $organizationSlug,
            );

            if (! $dist) {
                return;
            }

            $distUrl = url("/{$organizationSlug}/dists/{$package->name}/{$packageVersion->version}/{$packageVersion->source_reference}.zip");

            $packageVersion->update([
                'dist_url' => $distUrl,
                'dist_path' => $dist['path'],
                'dist_shasum' => $dist['shasum'],
            ]);
        } catch (MirrorDistDownloadException $e) {
            // Track dist download failures separately so they show in the sync log
            $this->incrementCounter('dist_failed');
            Cache::put(
                "sync-batch:{$this->batch()?->id}:dist_error",
                $e->getMessage(),
                now()->addHour(),
            );
        } catch (Throwable $e) {
            Log::warning('Failed to mirror dist archive', [
                'mirror' => $this->mirror->name,
                'package' => $package->name,
                'version' => $this->version,
                'error' => $e->getMessage(),
            ]);
        }
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
        Log::error('SyncMirrorVersionJob failed permanently', [
            'mirror' => $this->mirror->name ?? 'unknown',
            'mirror_uuid' => $this->mirror->uuid ?? 'unknown',
            'package' => $this->packageName,
            'version' => $this->version,
            'error' => $exception?->getMessage() ?? 'No exception provided',
        ]);

        $this->incrementCounter('failed');
    }
}
