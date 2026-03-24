<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateDistArchiveAction
{
    public function handle(
        GitProviderInterface $provider,
        PackageVersion $version,
        string $organizationSlug,
    ): ?DistArchiveData {
        if (! $version->source_reference) {
            return null;
        }

        $tempPath = sys_get_temp_dir().'/pricore-dist-'.Str::random(16).'.zip';

        try {
            if (! $provider->downloadArchive($version->source_reference, $tempPath)) {
                return null;
            }

            $shasum = hash_file('sha1', $tempPath);
            $size = filesize($tempPath);

            if ($shasum === false || $size === false) {
                return null;
            }

            $refShort = substr($version->source_reference, 0, 12);
            $storagePath = "{$organizationSlug}/{$version->package->name}/{$version->version}_{$refShort}.zip";

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
