<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Services\GitProviders\Contracts\GitProviderInterface;
use Illuminate\Support\Facades\DB;

class SyncRefAction
{
    public function __construct(
        protected FindOrCreatePackageAction $findOrCreatePackage
    ) {}

    /**
     * Sync a single ref (tag or branch).
     *
     * @return string added|updated|skipped
     */
    public function handle(
        GitProviderInterface $provider,
        Repository $repository,
        RefData $ref
    ): string {
        // First, get composer.json to extract package name
        $composerJson = $provider->getFileContent($ref->name, 'composer.json');

        if (! $composerJson) {
            return 'skipped';
        }

        $metadata = ComposerMetadataData::fromComposerJson($composerJson, $ref->name);

        // Check if this version already exists with the same commit SHA
        // If so, skip the expensive sync operation
        $package = Package::query()
            ->where('organization_uuid', $repository->organization_uuid)
            ->where('name', $metadata->name)
            ->first();

        if ($package) {
            $existingVersion = PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->where('version', $metadata->version)
                ->where('source_reference', $ref->commit)
                ->first();

            if ($existingVersion) {
                // Version exists with the same commit SHA - no changes needed
                return 'skipped';
            }
        }

        return DB::transaction(function () use ($metadata, $ref, $package, $repository): string {
            if (! $package) {
                $package = $this->findOrCreatePackage->handle($repository, $metadata->name);
            }

            $version = PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->where('version', $metadata->version)
                ->first();

            if ($version) {
                // Version exists but commit SHA changed - update it
                $version->update([
                    'normalized_version' => $metadata->normalizedVersion,
                    'composer_json' => $metadata->composerJson,
                    'source_reference' => $ref->commit,
                ]);

                return 'updated';
            }

            PackageVersion::create([
                'package_uuid' => $package->uuid,
                'version' => $metadata->version,
                'normalized_version' => $metadata->normalizedVersion,
                'composer_json' => $metadata->composerJson,
                'source_reference' => $ref->commit,
                'released_at' => now(),
            ]);

            return 'added';
        });
    }
}
