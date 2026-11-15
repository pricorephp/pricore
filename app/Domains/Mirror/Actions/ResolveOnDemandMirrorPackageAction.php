<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Contracts\Enums\MirrorSyncMode;
use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Domains\Mirror\Jobs\RefreshMirrorPackageJob;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class ResolveOnDemandMirrorPackageAction
{
    protected const int MISSING_TTL_MINUTES = 10;

    protected const int REFRESH_INTERVAL_MINUTES = 5;

    public function __construct(
        protected ImportMirrorPackageAction $importMirrorPackageAction,
    ) {}

    /**
     * Import a package Composer asked for from the organization's on-demand
     * mirrors, in the order they were added. Names no mirror has are
     * remembered for a while, since Composer asks for every dependency.
     *
     * @throws MirrorSyncException when a mirror fails to answer
     * @throws LockTimeoutException
     */
    public function handle(Organization $organization, string $packageName): ?Package
    {
        if (! MirrorSyncMode::onDemandEnabled()) {
            return null;
        }

        $mirrors = $organization->mirrors()
            ->where('sync_mode', MirrorSyncMode::OnDemand)
            ->oldest()
            ->get();

        if ($mirrors->isEmpty()) {
            return null;
        }

        // Keyed by the mirrors asked, so adding or removing one starts over.
        $mirrorSet = md5($mirrors->pluck('uuid')->join(','));
        $missingKey = "mirror-package-missing:{$organization->uuid}:{$mirrorSet}:{$packageName}";

        if (Cache::has($missingKey)) {
            return null;
        }

        // Composer requests the stable and dev metadata of a package at once;
        // both wait for a single import.
        return Cache::lock("mirror-package-import:{$organization->uuid}:{$packageName}", 120)
            ->block(60, function () use ($organization, $mirrors, $packageName, $missingKey): ?Package {
                $existingPackage = $organization->packages()->where('name', $packageName)->first();

                if ($existingPackage) {
                    return $existingPackage;
                }

                foreach ($mirrors as $mirror) {
                    $package = $this->importMirrorPackageAction->handle($mirror, $packageName);

                    if ($package) {
                        $this->markFresh($package);

                        return $package;
                    }
                }

                Cache::put($missingKey, true, now()->addMinutes(self::MISSING_TTL_MINUTES));

                return null;
            });
    }

    /**
     * Refresh a package of an on-demand mirror in the background when it was
     * last checked against upstream a while ago. The current metadata is
     * served meanwhile.
     */
    public function refreshIfStale(Package $package): void
    {
        if (! MirrorSyncMode::onDemandEnabled() || ! $package->mirror?->isOnDemand()) {
            return;
        }

        if (Cache::add($this->freshKey($package), true, now()->addMinutes(self::REFRESH_INTERVAL_MINUTES))) {
            RefreshMirrorPackageJob::dispatch($package);
        }
    }

    protected function markFresh(Package $package): void
    {
        Cache::put($this->freshKey($package), true, now()->addMinutes(self::REFRESH_INTERVAL_MINUTES));
    }

    protected function freshKey(Package $package): string
    {
        return "mirror-package-fresh:{$package->uuid}";
    }
}
