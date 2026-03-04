<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateDistArchiveAction
{
    /**
     * Create a dist archive for a package version.
     *
     * @return array{path: string, shasum: string}|null
     */
    public function handle(
        GitProviderInterface $provider,
        PackageVersion $version,
        string $organizationSlug,
    ): ?array {
        if (! $version->source_reference) {
            return null;
        }

        $tempPath = sys_get_temp_dir().'/pricore-dist-'.Str::random(16).'.zip';

        try {
            if (! $provider->downloadArchive($version->source_reference, $tempPath)) {
                return null;
            }

            $shasum = hash_file('sha1', $tempPath);

            if ($shasum === false) {
                return null;
            }

            $refShort = substr($version->source_reference, 0, 12);
            $storagePath = "{$organizationSlug}/{$version->package->name}/{$version->version}_{$refShort}.zip";

            $disk = Storage::disk(config('pricore.dist.disk'));
            $contents = file_get_contents($tempPath);

            if ($contents === false) {
                return null;
            }

            $disk->put($storagePath, $contents);

            return [
                'path' => $storagePath,
                'shasum' => $shasum,
            ];
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
