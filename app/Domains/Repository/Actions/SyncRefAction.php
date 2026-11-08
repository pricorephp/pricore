<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefTargetData;
use App\Domains\Repository\Contracts\Data\SyncRefResultData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;
use App\Domains\Repository\Services\TagFormats\TagFormat;
use App\Exceptions\ComposerMetadataException;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\RepositoryEmptyRef;
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
     * A tag in the repository's tag format syncs only the package it names.
     */
    public function handle(GitProviderInterface $provider, Repository $repository, RefData $ref): SyncRefResultData
    {
        $result = new SyncRefResultData;
        $format = TagFormat::forRepository($repository);
        $target = TagFormat::target($format, $ref);

        // A tag that is neither a version nor in the tag format releases nothing
        if ($target === null) {
            return $result;
        }

        $syncedPackageUuids = [];
        $unreadablePaths = [];
        $pathsByName = [];
        $composerJsonFound = false;
        $composerJsonMissing = false;

        [$paths, $narrowed] = $this->pathsFor($provider, $repository, $ref, $format, $target);
        $checkedPaths = [];

        while ($paths !== []) {
            foreach ($paths as $path) {
                $checkedPaths[] = $path;
                $composerJson = $provider->getFileContent($ref->name, self::join($path, 'composer.json'));

                if ($composerJson === null) {
                    $composerJsonMissing = true;
                    $result->skipped++;

                    continue;
                }

                $composerJsonFound = $composerJsonFound || $target->isShared();

                try {
                    $metadata = ComposerMetadataData::fromComposerJson($composerJson, $ref, $target->version);
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

                // Another package of the monorepo, which this tag does not release
                if (! $target->isShared() && $format?->keyFor($path, $metadata->name) !== $target->packageKey) {
                    continue;
                }

                $composerJsonFound = true;

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

                match ($this->syncVersion($provider, $repository, $ref, $package, $metadata, $path, $format, $target)) {
                    'added' => $result->added++,
                    'updated' => $result->updated++,
                    default => $result->skipped++,
                };
            }

            // The directory saved from an earlier sync is stale when the package
            // moved between tags, so look for it everywhere else
            $paths = $narrowed && ! $composerJsonFound
                ? array_values(array_diff($this->resolvePackagePathsAction->handle($provider, $repository, $ref->name), $checkedPaths))
                : [];
            $narrowed = false;
        }

        // Providers answer "not found" as well once access to the repository is
        // lost, which must not pass for packages removed at this ref
        if ($composerJsonMissing || $checkedPaths === []) {
            $provider->ensureRefResolves($ref->commit);
        }

        $result->packagesFound = count($syncedPackageUuids);
        $result->removed = $this->removeVanishedVersions($repository, $ref, $target, $format !== null, $syncedPackageUuids, $unreadablePaths);
        $result->empty = ! $composerJsonFound;

        $this->rememberWhetherEmpty($repository, $ref, $result->empty);

        return $result;
    }

    /**
     * A tag of a single package only needs that package's directory: named by the
     * tag under {path}, or known from an earlier sync under {name} and {package}.
     * Only a package not synced before needs every configured path checked.
     * Also returns whether the paths were narrowed down to a saved directory.
     *
     * @return array{0: array<int, string>, 1: bool}
     */
    protected function pathsFor(
        GitProviderInterface $provider,
        Repository $repository,
        RefData $ref,
        ?TagFormat $format,
        RefTargetData $target,
    ): array {
        if ($format === null || $target->isShared()) {
            return [$this->resolvePackagePathsAction->handle($provider, $repository, $ref->name), false];
        }

        $selected = $repository->package_paths ?: [PackagePathPattern::ROOT];

        if ($format->placeholder === TagFormat::PATH) {
            return [PackagePathPattern::anyMatches($selected, $target->packageKey) ? [(string) $target->packageKey] : [], false];
        }

        $known = $repository->packages()
            ->get(['name', 'source_path'])
            ->first(fn (Package $package) => $format->keyFor($package->source_path, $package->name) === $target->packageKey);

        if ($known !== null && PackagePathPattern::anyMatches($selected, $known->source_path)) {
            return [[(string) $known->source_path], true];
        }

        return [$this->resolvePackagePathsAction->handle($provider, $repository, $ref->name), false];
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
        ?TagFormat $format,
        RefTargetData $target,
    ): string {
        $sourcePath = $path === '' ? null : $path;

        $existingVersion = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->where('version', $metadata->version)
            ->first();

        // A tag of this package alone outranks a shared tag of the same version, once
        // it released that version. Until then, or when that tag holds no package,
        // the shared tag releases it, and the package tag takes it over later.
        $packageTag = $format !== null && $target->isShared()
            ? ($ref->packageTags[(string) $format->keyFor($path, $package->name)] ?? null)
            : null;

        if ($packageTag !== null && $existingVersion?->source_tag === $packageTag) {
            return 'skipped';
        }

        // Same commit at the same location: nothing changed, skip the expensive work
        if ($existingVersion
            && $existingVersion->source_reference === $ref->commit
            && $existingVersion->source_path === $sourcePath) {
            // A package tag at the commit a shared tag already released takes the
            // version over, so a later move of the shared tag leaves it alone
            if ($existingVersion->source_tag !== $ref->name && ! $target->isShared()) {
                $existingVersion->update(['source_tag' => $ref->name]);
            }

            // Retry an archive that failed to build. One removed on purpose,
            // such as by release retention, is not marked and stays removed.
            if (config('pricore.dist.enabled') && $existingVersion->dist_failed_at) {
                $this->createDistForVersion($provider, $existingVersion, $package, $repository);
            }

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

            if ($dist) {
                $this->recordDistArchiveAction->handle($version, $dist, $organizationSlug);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create dist archive', [
                'package' => $package->name,
                'version' => $version->version,
                'error' => $e->getMessage(),
            ]);
        }

        // Marks the version for a retry on the next sync.
        $version->update(['dist_failed_at' => now()]);
    }

    /**
     * A package without a composer.json at this ref (directory removed, package
     * renamed) must stop advertising the ref's version. Paths whose composer.json
     * could not be parsed are left alone: that package is still there, just broken.
     * With a tag format, a ref only removes versions it created itself, since other
     * packages can hold the same version from tags of their own.
     *
     * @param  array<int, string>  $syncedPackageUuids
     * @param  array<int, string>  $unreadablePaths
     */
    protected function removeVanishedVersions(
        Repository $repository,
        RefData $ref,
        RefTargetData $target,
        bool $usesTagFormat,
        array $syncedPackageUuids,
        array $unreadablePaths,
    ): int {
        $removed = 0;

        PackageVersion::query()
            ->whereIn('package_uuid', $repository->packages()->select('uuid'))
            ->whereNotIn('package_uuid', $syncedPackageUuids)
            ->where('version', $target->version)
            ->when($usesTagFormat, fn ($query) => $query->where('source_tag', $ref->name))
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

    /**
     * A ref without any composer.json leaves no package version behind, so
     * without this record every sync would fetch it again.
     */
    protected function rememberWhetherEmpty(Repository $repository, RefData $ref, bool $empty): void
    {
        if (! $empty) {
            $repository->emptyRefs()->where('name', $ref->name)->delete();

            return;
        }

        RepositoryEmptyRef::query()->updateOrCreate(
            ['repository_uuid' => $repository->uuid, 'name' => $ref->name],
            ['commit' => $ref->commit, 'checked_at' => now()],
        );
    }

    protected static function join(string $path, string $file): string
    {
        return $path === '' ? $file : "{$path}/{$file}";
    }
}
