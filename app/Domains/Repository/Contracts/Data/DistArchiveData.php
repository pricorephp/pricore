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
     *
     * Every part can come from outside (an upstream mirror, a Git ref), so a
     * part that could leave the organization's directory is refused here.
     *
     * @throws InvalidArgumentException
     */
    public static function pathFor(
        string $organizationSlug,
        string $packageName,
        string $version,
        string $reference,
    ): string {
        if (! self::isPathSafeSegment($organizationSlug)
            || ! ComposerMetadataData::isPathSafeName($packageName)
            || ! self::isPathSafeVersion($version)
            || ! self::isPathSafeReference($reference)) {
            throw new InvalidArgumentException(
                "Refusing an unsafe dist archive path for {$packageName} {$version}."
            );
        }

        $referenceShort = substr($reference, 0, 12);

        return "{$organizationSlug}/{$packageName}/{$version}_{$referenceShort}.zip";
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

    /**
     * Versions may contain slashes (dev-feature/foo), so they are checked per
     * segment rather than against a strict pattern.
     */
    public static function isPathSafeVersion(string $version): bool
    {
        if ($version === '' || preg_match('/[\\\\\x00-\x1F\x7F]/', $version) === 1) {
            return false;
        }

        foreach (explode('/', $version) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The reference ends up inside the archive's file name, so it may not
     * contain a path separator.
     */
    public static function isPathSafeReference(string $reference): bool
    {
        return preg_match('{[/\\\\\x00-\x1F\x7F]}', $reference) !== 1;
    }

    /**
     * Whether a stored path is free of traversal. Paths recorded before
     * upstream metadata was validated may not be. The organization prefix is
     * not checked, because renaming an organization leaves its archives under
     * the old slug.
     */
    public static function isSafeStoredPath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return false;
            }
        }

        return true;
    }

    protected static function isPathSafeSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z0-9_.-]+$/', $segment) === 1
            && ! in_array($segment, ['.', '..'], true);
    }
}
