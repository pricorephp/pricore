<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\SyncRefResultData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Exceptions\ComposerMetadataException;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncRefAction
{
    public function __construct(
        protected ResolvePackagePathsAction $resolvePackagePathsAction,
        protected FindOrCreatePackageAction $findOrCreatePackageAction,
        protected FetchReadmeAction $fetchReadmeAction,
        protected CreateDistArchiveAction $createDistArchiveAction,
        protected RecordDistArchiveAction $recordDistArchiveAction,
        protected DetachDistArchivesTask $detachDistArchivesTask,
    ) {}

    /**
     * Sync every package present at a ref (tag or branch), then drop the ref's
     * version from packages of this repository that are no longer found at it.
     */
    public function handle(GitProviderInterface $provider, Repository $repository, RefData $ref): SyncRefResultData
    {
        $result = new SyncRefResultData;
        $syncedPackageUuids = [];
        $unreadablePaths = [];
        $pathsByName = [];

        foreach ($this->resolvePackagePathsAction->handle($provider, $repository, $ref->name) as $path) {
            $composerJson = $provider->getFileContent($ref->name, self::join($path, 'composer.json'));

            if ($composerJson === null) {
                $result->skipped++;

                continue;
            }

            try {
                $metadata = ComposerMetadataData::fromComposerJson($composerJson, $ref->name);
            } catch (ComposerMetadataException $e) {
                Log::warning('Skipping package with invalid composer.json', [
                    'repository' => $repository->name,
                    'ref' => $ref->name,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                $unreadablePaths[] = $path;
                $result->skipped++;

                continue;
            }

            if (isset($pathsByName[$metadata->name])) {
                Log::warning('Skipping duplicate package name within ref', [
                    'repository' => $repository->name,
                    'ref' => $ref->name,
                    'package' => $metadata->name,
                    'path' => $path,
                    'first_path' => $pathsByName[$metadata->name],
                ]);

                $result->skipped++;

                continue;
            }

            $pathsByName[$metadata->name] = $path;

            $package = $this->findOrCreatePackageAction->handle($repository, $metadata->name, $path);

            if ($package === null) {
                $result->skipped++;

                continue;
            }

            $syncedPackageUuids[] = $package->uuid;

            match ($this->syncVersion($provider, $repository, $ref, $package, $metadata, $path)) {
                'added' => $result->added++,
                'updated' => $result->updated++,
                default => $result->skipped++,
            };
        }

        $result->packagesFound = count($syncedPackageUuids);
        $result->removed = $this->removeVanishedVersions($repository, $ref, $syncedPackageUuids, $unreadablePaths);

        return $result;
    }

    /**
     * @return 'added'|'updated'|'skipped'
     */
    protected function syncVersion(
        GitProviderInterface $provider,
        Repository $repository,
        RefData $ref,
        Package $package,
        ComposerMetadataData $metadata,
        string $path,
    ): string {
        $sourcePath = $path === '' ? null : $path;

        $existingVersion = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->where('version', $metadata->version)
            ->first();

        // Same commit at the same location: nothing changed, skip the expensive work
        if ($existingVersion
            && $existingVersion->source_reference === $ref->commit
            && $existingVersion->source_path === $sourcePath) {
            return 'skipped';
        }

        $readme = $this->fetchReadmeAction->handle($provider, $ref->name, $path);
        $sourceUrl = $provider->getRepositoryUrl();

        $result = DB::transaction(function () use ($metadata, $ref, $package, $sourceUrl, $sourcePath, $readme): array {
            $version = PackageVersion::query()
                ->where('package_uuid', $package->uuid)
                ->where('version', $metadata->version)
                ->first();

            if ($version) {
                // The archive still describes the old commit, so release it
                // before the reference moves. Same transaction: a stale pointer
                // would serve the previous archive under the new reference.
                $this->detachDistArchivesTask->handle($version);

                $version->update([
                    'normalized_version' => $metadata->normalizedVersion,
                    'composer_json' => $metadata->composerJson,
                    'readme' => $readme,
                    'source_url' => $sourceUrl,
                    'source_reference' => $ref->commit,
                    'source_tag' => $ref->name,
                    'source_path' => $sourcePath,
                ]);

                return ['status' => 'updated', 'version' => $version];
            }

            $version = PackageVersion::create([
                'package_uuid' => $package->uuid,
                'version' => $metadata->version,
                'normalized_version' => $metadata->normalizedVersion,
                'composer_json' => $metadata->composerJson,
                'readme' => $readme,
                'source_url' => $sourceUrl,
                'source_reference' => $ref->commit,
                'source_tag' => $ref->name,
                'source_path' => $sourcePath,
                'released_at' => now(),
            ]);

            return ['status' => 'added', 'version' => $version];
        });

        if (config('pricore.dist.enabled')) {
            $this->createDistForVersion($provider, $result['version'], $package, $repository);
        }

        return $result['status'];
    }

    protected function createDistForVersion(
        GitProviderInterface $provider,
        PackageVersion $version,
        Package $package,
        Repository $repository,
    ): void {
        try {
            $organizationSlug = $repository->organization->slug;

            $dist = $this->createDistArchiveAction->handle($provider, $version, $organizationSlug);

            if (! $dist) {
                return;
            }

            $this->recordDistArchiveAction->handle($version, $dist, $organizationSlug);
        } catch (\Throwable $e) {
            Log::warning('Failed to create dist archive', [
                'package' => $package->name,
                'version' => $version->version,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A package without a composer.json at this ref (directory removed, package
     * renamed) must stop advertising the ref's version. Paths whose composer.json
     * could not be parsed are left alone: that package is still there, just broken.
     *
     * @param  array<int, string>  $syncedPackageUuids
     * @param  array<int, string>  $unreadablePaths
     */
    protected function removeVanishedVersions(
        Repository $repository,
        RefData $ref,
        array $syncedPackageUuids,
        array $unreadablePaths,
    ): int {
        $removed = 0;

        PackageVersion::query()
            ->whereIn('package_uuid', $repository->packages()->select('uuid'))
            ->whereNotIn('package_uuid', $syncedPackageUuids)
            ->where('version', ComposerMetadataData::extractVersion($ref->name))
            ->get()
            ->reject(fn (PackageVersion $version) => in_array((string) $version->source_path, $unreadablePaths, true))
            ->each(function (PackageVersion $version) use (&$removed) {
                $version->delete();
                $removed++;
            });

        if ($removed > 0) {
            Log::info('Removed versions of packages no longer present at ref', [
                'repository' => $repository->name,
                'ref' => $ref->name,
                'versions_removed' => $removed,
            ]);
        }

        return $removed;
    }

    protected static function join(string $path, string $file): string
    {
        return $path === '' ? $file : "{$path}/{$file}";
    }
}
