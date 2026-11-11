<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\RequestDistArchiveRebuildAction;
use App\Domains\Composer\Actions\ResolveDistArchiveAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
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
        ResolveDistArchiveAction $resolveDistArchiveAction,
        RequestDistArchiveRebuildAction $requestDistArchiveRebuildAction,
    ): Response {
        $distPath = $resolveDistArchiveAction->handle(
            organization: $organization,
            packageName: "{$vendor}/{$package}",
            version: $version,
            reference: $reference,
        );

        if (! $distPath && $requestDistArchiveRebuildAction->handle($organization, "{$vendor}/{$package}", $version, $reference)) {
            // Composer retries a 503 up to three times, waiting as long as
            // Retry-After asks (60 seconds at most), before falling back to source
            return response()->json(['error' => 'The archive is being built, retry shortly'], 503, [
                'Retry-After' => '60',
                'Cache-Control' => 'no-store',
            ]);
        }

        if (! $distPath) {
            return response()->json(['error' => 'Not found'], 404);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('pricore.dist.disk'));

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
