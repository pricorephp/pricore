<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Contracts\Enums\SyncVersionResult;
use App\Domains\Mirror\Exceptions\MirrorPackageNotFoundException;
use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Mirror;
use App\Models\Package;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

class ImportMirrorPackageAction
{
    public function __construct(
        protected FindOrCreateMirrorPackageAction $findOrCreateMirrorPackageAction,
        protected SyncMirrorPackageVersionAction $syncMirrorPackageVersionAction,
        protected RemoveStaleMirrorVersionsAction $removeStaleMirrorVersionsAction,
    ) {}

    /**
     * Bring one package of an on-demand mirror in line with upstream: add and
     * update its versions and remove the ones that are gone. Archives are left
     * to be fetched on their first download.
     *
     * Returns null when upstream does not have the package.
     *
     * @throws MirrorSyncException when upstream fails to answer
     */
    public function handle(Mirror $mirror, string $packageName): ?Package
    {
        // Taken before the list is read, so removing stale versions cannot
        // delete one that a concurrent sync writes after this read.
        $listedAt = now();

        try {
            $versions = RegistryClientFactory::make($mirror)->getPackageVersions($packageName);
        } catch (MirrorPackageNotFoundException) {
            return null;
        } catch (ConnectionException $exception) {
            throw new MirrorSyncException(
                "Failed to fetch metadata for {$packageName}: could not connect to {$mirror->url}",
                previous: $exception,
            );
        }

        if ($versions === []) {
            return null;
        }

        $changed = false;

        // Other requests must not see the package until all of its versions
        // are written, or Composer could cache an incomplete version list.
        $package = DB::transaction(function () use ($mirror, $packageName, $versions, $listedAt, &$changed): ?Package {
            $package = $this->findOrCreateMirrorPackageAction->handle($mirror, $packageName);

            if (! $package || $package->mirror_uuid !== $mirror->uuid) {
                return null;
            }

            foreach ($versions as $version => $composerJson) {
                $result = $this->syncMirrorPackageVersionAction->handle($package, (string) $version, $composerJson);

                $changed = $changed || $result !== SyncVersionResult::Skipped;
            }

            $this->removeStaleMirrorVersionsAction->handle($mirror, [$packageName => $versions], $listedAt);

            $package->touch();

            return $package;
        });

        // Scheduled advisory checks only look at packages again when the
        // advisory feed changes, so new versions are scanned right away.
        if ($package && $changed) {
            ScanPackageVersionsJob::dispatch($package);
        }

        return $package;
    }
}
