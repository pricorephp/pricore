<?php

namespace App\Domains\Package\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Package\Contracts\Data\ArtifactContentsData;
use App\Domains\Package\Contracts\Data\PublishedArtifactData;
use App\Domains\Package\Contracts\Enums\ArtifactPublishResult;
use App\Domains\Package\Exceptions\ArtifactRejectedException;
use App\Domains\Package\Services\Artifact\ArtifactInspector;
use App\Domains\Repository\Actions\DetachDistArchivesTask;
use App\Domains\Repository\Actions\RecordDistArchiveAction;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\AccessToken;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;
use Composer\Semver\VersionParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnexpectedValueException;

class PublishArtifactAction
{
    /**
     * Composer's own package name rule. Names end up in storage paths and
     * download URLs, so anything looser could reach outside the package's
     * directory on the dist disk.
     */
    public const NAME_PATTERN = '{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}';

    /**
     * Stricter than Git branch versions: no slashes, so the version is always
     * a single path segment of the stored archive.
     */
    protected const VERSION_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._+-]{0,99}$/';

    /**
     * Keys Pricore sets itself when serving metadata. Keeping an uploaded
     * "source" would let an archive point --prefer-source installs anywhere.
     *
     * @var list<string>
     */
    protected const RESERVED_COMPOSER_KEYS = [
        'dist',
        'source',
        'version',
        'version_normalized',
        'time',
        'notification-url',
        'installation-source',
    ];

    public function __construct(
        protected ArtifactInspector $artifactInspector,
        protected DetachDistArchivesTask $detachDistArchivesTask,
        protected RecordDistArchiveAction $recordDistArchiveAction,
        protected RecordActivityTask $recordActivityTask,
    ) {}

    /**
     * Publish an uploaded zip as a package version.
     *
     * Passing $package pins the upload to that package; otherwise the package
     * is looked up by the name in composer.json and, when $allowCreatingPackage
     * is set, created. Stable versions are immutable: an identical re-upload is
     * a no-op, a different archive is rejected. Dev versions are replaced.
     */
    public function handle(
        Organization $organization,
        string $archivePath,
        ?string $version = null,
        ?Package $package = null,
        bool $allowCreatingPackage = false,
        ?User $actor = null,
        ?AccessToken $accessToken = null,
    ): PublishedArtifactData {
        $contents = $this->artifactInspector->inspect($archivePath);
        $name = $this->packageName($contents);

        if ($package !== null && $package->name !== $name) {
            throw ArtifactRejectedException::invalid("The archive is for {$name}, not {$package->name}.");
        }

        [$version, $normalizedVersion] = $this->resolveVersion($contents, $version);

        $packageCreated = false;
        $package = $package !== null
            ? $this->ensureArtifactPackage($package)
            : $this->findOrCreatePackage($organization, $name, $contents, $allowCreatingPackage, $packageCreated);

        $shasum = (string) hash_file('sha1', $archivePath);
        $size = (int) filesize($archivePath);

        [$packageVersion, $result] = $this->storeVersion(
            organization: $organization,
            package: $package,
            contents: $contents,
            archivePath: $archivePath,
            version: $version,
            normalizedVersion: $normalizedVersion,
            shasum: $shasum,
            size: $size,
        );

        if ($packageCreated) {
            $this->recordActivityTask->handle(
                organization: $organization,
                type: ActivityType::PackageCreated,
                subject: $package,
                actor: $actor,
                properties: array_filter([
                    'name' => $package->name,
                    'uploaded' => true,
                    'token_name' => $accessToken?->name,
                ]),
            );
        }

        if (! $result->isUnchanged()) {
            $this->recordActivityTask->handle(
                organization: $organization,
                type: ActivityType::PackageVersionUploaded,
                subject: $package,
                actor: $actor,
                properties: array_filter([
                    'name' => $package->name,
                    'version' => $version,
                    'replaced' => $result === ArtifactPublishResult::Replaced,
                    'shasum' => $shasum,
                    'size' => $size,
                    'token_name' => $accessToken?->name,
                ]),
            );

            ScanPackageVersionsJob::dispatch($package);
        }

        return new PublishedArtifactData(
            package: $package,
            version: $packageVersion,
            result: $result,
            packageCreated: $packageCreated,
        );
    }

    protected function packageName(ArtifactContentsData $contents): string
    {
        $name = $contents->composerJson['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw ArtifactRejectedException::invalid('composer.json is missing required field: name');
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw ArtifactRejectedException::invalid("\"{$name}\" is not a valid package name. Use a lowercase vendor/package name.");
        }

        return $name;
    }

    /**
     * @return array{0: string, 1: string} The version and its normalized form
     */
    protected function resolveVersion(ArtifactContentsData $contents, ?string $version): array
    {
        $version = trim((string) $version);

        if ($version === '') {
            $version = $contents->composerJson['version'] ?? null;
        }

        if (! is_string($version) || $version === '') {
            throw ArtifactRejectedException::invalid('No version given. Pass a version with the upload or set "version" in composer.json.');
        }

        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw ArtifactRejectedException::invalid("\"{$version}\" is not a valid version.");
        }

        try {
            return [$version, (new VersionParser)->normalize($version)];
        } catch (UnexpectedValueException) {
            throw ArtifactRejectedException::invalid("\"{$version}\" is not a valid version.");
        }
    }

    protected function ensureArtifactPackage(Package $package): Package
    {
        if (! $package->is_artifact) {
            throw ArtifactRejectedException::conflict("{$package->name} is synced from a repository or mirror, so versions can't be uploaded to it.");
        }

        return $package;
    }

    protected function findOrCreatePackage(
        Organization $organization,
        string $name,
        ArtifactContentsData $contents,
        bool $allowCreatingPackage,
        bool &$packageCreated,
    ): Package {
        $package = $organization->packages()->where('name', $name)->first();

        if ($package !== null) {
            return $this->ensureArtifactPackage($package);
        }

        if (! $allowCreatingPackage) {
            throw ArtifactRejectedException::notFound("Package {$name} does not exist. An admin can create it by uploading its first version in Pricore.");
        }

        try {
            $package = $organization->packages()->create([
                'name' => $name,
                'description' => $this->description($contents),
                'type' => $this->type($contents),
                'visibility' => 'private',
                'is_artifact' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another upload created it in the meantime
            return $this->ensureArtifactPackage(
                $organization->packages()->where('name', $name)->firstOrFail()
            );
        }

        $packageCreated = true;

        return $package;
    }

    /**
     * Runs under a lock on the package row, so concurrent uploads of the same
     * version are serialized and the loser sees the winner's archive.
     *
     * @return array{0: PackageVersion, 1: ArtifactPublishResult}
     */
    protected function storeVersion(
        Organization $organization,
        Package $package,
        ArtifactContentsData $contents,
        string $archivePath,
        string $version,
        string $normalizedVersion,
        string $shasum,
        int $size,
    ): array {
        $disk = Storage::disk(config('pricore.dist.disk'));
        $storagePath = DistArchiveData::pathFor(
            organizationSlug: $organization->slug,
            packageName: $package->name,
            version: $version,
            reference: $shasum,
        );
        $stored = false;

        try {
            return DB::transaction(function () use ($organization, $package, $contents, $archivePath, $version, $normalizedVersion, $shasum, $size, $disk, $storagePath, &$stored): array {
                Package::query()->whereKey($package->uuid)->lockForUpdate()->first();

                /** @var PackageVersion|null $existingVersion */
                $existingVersion = $package->versions()->where('version', $version)->first();

                if ($existingVersion?->source_reference === $shasum) {
                    return [$existingVersion, ArtifactPublishResult::Unchanged];
                }

                if ($existingVersion === null) {
                    // 1.0.0 and v1.0.0 are the same release to Composer and share a download URL
                    $equivalentVersion = $package->versions()->where('normalized_version', $normalizedVersion)->value('version');

                    if ($equivalentVersion !== null) {
                        throw ArtifactRejectedException::conflict("Version {$version} of {$package->name} already exists as {$equivalentVersion}.");
                    }
                } elseif (! $this->isDevVersion($normalizedVersion)) {
                    throw ArtifactRejectedException::conflict("Version {$version} of {$package->name} already exists. Published releases can't be replaced; upload a new version instead.");
                }

                $stream = fopen($archivePath, 'r');

                if ($stream === false) {
                    throw new \RuntimeException('Could not read the uploaded archive.');
                }

                try {
                    $stored = $disk->put($storagePath, $stream);
                } finally {
                    fclose($stream);
                }

                if (! $stored) {
                    throw new \RuntimeException('Could not store the uploaded archive.');
                }

                $attributes = [
                    'normalized_version' => $normalizedVersion,
                    'composer_json' => Arr::except($contents->composerJson, self::RESERVED_COMPOSER_KEYS),
                    'readme' => $contents->readme,
                    'source_url' => null,
                    'source_reference' => $shasum,
                    'source_tag' => null,
                    'source_path' => null,
                    'released_at' => $this->releasedAt($contents),
                ];

                if ($existingVersion !== null) {
                    // A dev build replaced: the old archive stays on disk,
                    // detached, for lock files that still pin its shasum.
                    $this->detachDistArchivesTask->handle($existingVersion);
                    $existingVersion->update($attributes);
                    $packageVersion = $existingVersion;
                    $result = ArtifactPublishResult::Replaced;
                } else {
                    $packageVersion = $package->versions()->create(['version' => $version, ...$attributes]);
                    $result = ArtifactPublishResult::Added;
                }

                $packageVersion->setRelation('package', $package);

                $this->recordDistArchiveAction->handle(
                    version: $packageVersion,
                    archive: new DistArchiveData(path: $storagePath, shasum: $shasum, size: $size),
                    organizationSlug: $organization->slug,
                );

                if (! $this->isDevVersion($normalizedVersion)) {
                    $package->update([
                        'description' => $this->description($contents),
                        'type' => $this->type($contents),
                    ]);
                }

                $package->touch();

                return [$packageVersion->refresh(), $result];
            });
        } catch (Throwable $e) {
            // A dev build re-uploaded after being replaced reuses the path of
            // its detached archive, which lock files may still point at
            if ($stored && ! DistArchive::query()->where('path', $storagePath)->exists()) {
                $disk->delete($storagePath);
            }

            throw $e;
        }
    }

    protected function isDevVersion(string $normalizedVersion): bool
    {
        return str_starts_with($normalizedVersion, 'dev-') || str_ends_with($normalizedVersion, '-dev');
    }

    protected function releasedAt(ArtifactContentsData $contents): Carbon
    {
        $time = $contents->composerJson['time'] ?? null;

        if (is_string($time) && $time !== '') {
            try {
                $releasedAt = Carbon::parse($time);

                if ($releasedAt->isPast()) {
                    return $releasedAt;
                }
            } catch (Throwable) {
                // Fall through to the upload time
            }
        }

        return now();
    }

    protected function description(ArtifactContentsData $contents): ?string
    {
        $description = $contents->composerJson['description'] ?? null;

        return is_string($description) && $description !== '' ? mb_substr($description, 0, 1000) : null;
    }

    protected function type(ArtifactContentsData $contents): string
    {
        $type = $contents->composerJson['type'] ?? null;

        return is_string($type) && $type !== '' ? mb_substr($type, 0, 100) : 'library';
    }
}
