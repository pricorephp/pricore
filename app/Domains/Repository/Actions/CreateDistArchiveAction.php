<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateDistArchiveAction
{
    /**
     * Build the archive of a package at a commit, before any version points at it.
     */
    public function handle(
        GitProviderInterface $provider,
        string $organizationSlug,
        string $packageName,
        string $version,
        string $reference,
        ?string $sourcePath,
    ): ?DistArchiveData {
        $tempPath = sys_get_temp_dir().'/pricore-dist-'.Str::random(16).'.zip';

        try {
            if (! $provider->downloadArchive($reference, $tempPath, $sourcePath)) {
                return null;
            }

            $shasum = hash_file('sha1', $tempPath);
            $size = filesize($tempPath);

            if ($shasum === false || $size === false) {
                return null;
            }

            $storagePath = DistArchiveData::pathFor(
                organizationSlug: $organizationSlug,
                packageName: $packageName,
                version: $version,
                reference: $reference,
            );

            $disk = Storage::disk(config('pricore.dist.disk'));
            $stream = fopen($tempPath, 'r');

            if ($stream === false) {
                return null;
            }

            try {
                $disk->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            return new DistArchiveData(
                path: $storagePath,
                shasum: $shasum,
                size: $size,
            );
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
