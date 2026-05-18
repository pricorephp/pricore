<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncRefAction
{
    public function __construct(
        protected FindOrCreatePackageAction $findOrCreatePackage,
        protected CreateDistArchiveAction $createDistArchive,
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

        $releasedAt = $this->resolveReleasedAt($provider, $ref, $metadata);

        $result = DB::transaction(function () use ($metadata, $ref, $package, $repository, $provider, $releasedAt): array {
            if (! $package) {
                $package = $this->findOrCreatePackage->handle($repository, $metadata->name);
            }

            $sourceUrl = $provider->getRepositoryUrl();

            $version = PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->where('version', $metadata->version)
                ->first();

            if ($version) {
                // Version exists but commit SHA changed - update it
                $version->update([
                    'normalized_version' => $metadata->normalizedVersion,
                    'composer_json' => $metadata->composerJson,
                    'source_url' => $sourceUrl,
                    'source_reference' => $ref->commit,
                    'source_tag' => $ref->name,
                    'released_at' => $releasedAt,
                ]);

                return ['status' => 'updated', 'version' => $version, 'package' => $package];
            }

            $version = PackageVersion::create([
                'package_uuid' => $package->uuid,
                'version' => $metadata->version,
                'normalized_version' => $metadata->normalizedVersion,
                'composer_json' => $metadata->composerJson,
                'source_url' => $sourceUrl,
                'source_reference' => $ref->commit,
                'source_tag' => $ref->name,
                'released_at' => $releasedAt,
            ]);

            return ['status' => 'added', 'version' => $version, 'package' => $package];
        });

        if (config('pricore.dist.enabled')) {
            $this->createDistForVersion($provider, $result['version'], $result['package'], $repository);
        }

        return $result['status'];
    }

    protected function resolveReleasedAt(
        GitProviderInterface $provider,
        RefData $ref,
        ComposerMetadataData $metadata,
    ): CarbonImmutable {
        $commitDate = $provider->getCommitDate($ref->commit);

        if ($commitDate) {
            return $commitDate;
        }

        if (isset($metadata->composerJson['time']) && is_string($metadata->composerJson['time'])) {
            try {
                return CarbonImmutable::parse($metadata->composerJson['time']);
            } catch (\Exception) {
                // fall through to now()
            }
        }

        return CarbonImmutable::now();
    }

    protected function createDistForVersion(
        GitProviderInterface $provider,
        PackageVersion $version,
        Package $package,
        Repository $repository,
    ): void {
        try {
            $organizationSlug = $repository->organization->slug;

            $dist = $this->createDistArchive->handle($provider, $version, $organizationSlug);

            if (! $dist) {
                return;
            }

            $distUrl = url("/{$organizationSlug}/dists/{$package->name}/{$version->version}/{$version->source_reference}.zip");

            $version->update([
                'dist_url' => $distUrl,
                'dist_path' => $dist->path,
                'dist_shasum' => $dist->shasum,
                'dist_size' => $dist->size,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create dist archive', [
                'package' => $package->name,
                'version' => $version->version,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
