<?php

namespace App\Domains\Repository\Contracts\Data;

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
}
