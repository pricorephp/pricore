<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Exceptions\MirrorPackageNotFoundException;
use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Models\Mirror;
use App\Models\Package;

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
        try {
            $versions = RegistryClientFactory::make($mirror)->getPackageVersions($packageName);
        } catch (MirrorPackageNotFoundException) {
            return null;
        }

        if ($versions === []) {
            return null;
        }

        $package = $this->findOrCreateMirrorPackageAction->handle($mirror, $packageName);

        if (! $package || $package->mirror_uuid !== $mirror->uuid) {
            return null;
        }

        foreach ($versions as $version => $composerJson) {
            $this->syncMirrorPackageVersionAction->handle($package, (string) $version, $composerJson);
        }

        $this->removeStaleMirrorVersionsAction->handle($mirror, [$packageName => $versions]);

        $package->touch();

        return $package;
    }
}
