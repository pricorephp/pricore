<?php

namespace App\Domains\Mirror\Services\RegistryClient;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use Illuminate\Support\Facades\Log;

/**
 * Package names, versions and references from an upstream registry become
 * parts of dist archive paths, and anyone can point a mirror at a registry
 * they control. Entries that could not be stored safely are left out.
 */
class UpstreamMetadataFilter
{
    public static function isSafePackageName(mixed $packageName): bool
    {
        return is_string($packageName) && ComposerMetadataData::isPathSafeName($packageName);
    }

    /**
     * @param  array<int, mixed>  $packageNames
     * @return array<int, string>
     */
    public static function packageNames(array $packageNames): array
    {
        return array_values(array_filter($packageNames, static::isSafePackageName(...)));
    }

    /**
     * @param  array<int|string, mixed>  $versions  version => composer.json data
     * @return array<string, array<string, mixed>>
     */
    public static function versions(string $packageName, array $versions): array
    {
        $safeVersions = [];

        foreach ($versions as $version => $composerJson) {
            $version = (string) $version;

            if (is_array($composerJson) && static::isSafeVersion($version, $composerJson)) {
                $safeVersions[$version] = $composerJson;

                continue;
            }

            Log::warning('Skipped mirror version with unsafe metadata', [
                'package' => $packageName,
                'version' => $version,
            ]);
        }

        return $safeVersions;
    }

    /**
     * @param  array<string, mixed>  $composerJson
     */
    protected static function isSafeVersion(string $version, array $composerJson): bool
    {
        if (! DistArchiveData::isPathSafeVersion($version)) {
            return false;
        }

        // Only the reference sync stores is used in the archive path: the dist
        // reference, falling back to the source reference.
        $reference = $composerJson['dist']['reference'] ?? $composerJson['source']['reference'] ?? null;

        if ($reference === null) {
            return true;
        }

        return is_scalar($reference) && DistArchiveData::isPathSafeReference((string) $reference);
    }
}
