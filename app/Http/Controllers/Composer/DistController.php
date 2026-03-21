<?php

namespace App\Http\Controllers\Composer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PackageVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistController extends Controller
{
    public function download(
        Organization $organization,
        string $vendor,
        string $package,
        string $version,
        string $reference,
    ): Response {
        $packageName = "{$vendor}/{$package}";

        $packageVersion = PackageVersion::query()
            ->whereHas('package', function ($query) use ($organization, $packageName) {
                $query->where('organization_uuid', $organization->uuid)
                    ->where('name', $packageName);
            })
            ->where('version', $version)
            ->where('source_reference', $reference)
            ->whereNotNull('dist_path')
            ->first();

        if (! $packageVersion || ! $packageVersion->dist_path) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $distPath = $packageVersion->dist_path;

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('pricore.dist.disk'));

        if (! $disk->exists($distPath)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        /** @var string $diskName */
        $diskName = config('pricore.dist.disk');

        /** @var array{driver?: string} $diskConfig */
        $diskConfig = config("filesystems.disks.{$diskName}", []);

        if (($diskConfig['driver'] ?? 'local') === 's3') {
            /** @var int $expiry */
            $expiry = config('pricore.dist.signed_url_expiry', 30);

            $url = $disk->temporaryUrl($distPath, now()->addMinutes($expiry));

            return redirect($url)->header('Cache-Control', 'private, no-store');
        }

        /** @var StreamedResponse $response */
        $response = $disk->download($distPath);

        $response->headers->set('Cache-Control', 'private, max-age=31536000, immutable');
        $response->headers->set('ETag', '"'.$reference.'"');

        return $response;
    }
}
