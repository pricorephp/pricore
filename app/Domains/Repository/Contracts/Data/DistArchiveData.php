<?php

namespace App\Domains\Repository\Contracts\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

class DistArchiveData extends Data
{
    public function __construct(
        public string $path,
        public string $shasum,
        public int $size,
    ) {}

    /**
     * Storage path for an archive. Deterministic on purpose: downloads resolve
     * archives for a commit the version row no longer points at by rebuilding
     * this path, so every writer must derive it here.
     */
    public static function pathFor(
        string $organizationSlug,
        string $packageName,
        string $version,
        string $reference,
    ): string {
        $referenceShort = substr($reference, 0, 12);

        if (! ComposerMetadataData::isPathSafeName($packageName) || ! static::isPathSafeVersion("{$version}_{$referenceShort}")) {
            throw new InvalidArgumentException("Refusing to build a dist path for {$packageName} {$version}.");
        }

        return "{$organizationSlug}/{$packageName}/{$version}_{$referenceShort}.zip";
    }

    /**
     * Versions come from Git refs and mirrored registries and become part of
     * the storage path. Branch names may hold slashes, but no segment may step
     * out of the package's own directory on the dist disk.
     */
    public static function isPathSafeVersion(string $version): bool
    {
        if ($version === '' || preg_match('/[\\\\[:cntrl:]]/', $version) === 1) {
            return false;
        }

        return array_intersect(explode('/', $version), ['', '.', '..']) === [];
    }

    /**
     * Public download URL for an archive, as advertised in Composer metadata.
     * Unlike the storage path this carries the full reference.
     */
    public static function urlFor(
        string $organizationSlug,
        string $packageName,
        string $version,
        string $reference,
    ): string {
        return url("/{$organizationSlug}/dists/{$packageName}/{$version}/{$reference}.zip");
    }
}
