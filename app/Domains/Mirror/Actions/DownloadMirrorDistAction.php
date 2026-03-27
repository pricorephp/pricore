<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Exceptions\MirrorDistDownloadException;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Models\Mirror;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadMirrorDistAction
{
    public function handle(
        Mirror $mirror,
        PackageVersion $packageVersion,
        Package $package,
        string $organizationSlug,
    ): ?DistArchiveData {
        $distUrl = $packageVersion->composer_json['dist']['url'] ?? null;

        if (! $distUrl) {
            return null;
        }

        $tempPath = sys_get_temp_dir().'/pricore-mirror-dist-'.Str::random(16).'.zip';

        try {
            $httpClient = RegistryClientFactory::createHttpClient($mirror);

            $response = $httpClient
                ->withOptions(['sink' => $tempPath])
                ->get($distUrl);

            if (! $response->successful()) {
                $status = $response->status();
                $message = match (true) {
                    $status === 401 => 'Authentication failed — check your credentials.',
                    $status === 403 => 'Access denied — the provided credentials may not have permission to download dist archives.',
                    default => "HTTP {$status}",
                };

                Log::warning('Failed to download mirror dist archive', [
                    'mirror' => $mirror->name,
                    'package' => $package->name,
                    'version' => $packageVersion->version,
                    'dist_url' => $distUrl,
                    'status' => $status,
                    'message' => $message,
                ]);

                throw new MirrorDistDownloadException(
                    "Dist download failed for {$package->name} {$packageVersion->version}: {$message}"
                );
            }

            $shasum = hash_file('sha1', $tempPath);
            $size = filesize($tempPath);

            if ($shasum === false || $size === false) {
                return null;
            }

            $refShort = substr($packageVersion->source_reference ?? '', 0, 12);
            $storagePath = "{$organizationSlug}/{$package->name}/{$packageVersion->version}_{$refShort}.zip";

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
