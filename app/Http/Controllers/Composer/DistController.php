<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\ResolveDistArchiveAction;
use App\Domains\Mirror\Actions\FetchMirrorDistOnDemandAction;
use App\Domains\Mirror\Exceptions\MirrorDistDownloadException;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        FetchMirrorDistOnDemandAction $fetchMirrorDistOnDemandAction,
    ): Response {
        $distPath = $resolveDistArchiveAction->handle(
            organization: $organization,
            packageName: "{$vendor}/{$package}",
            version: $version,
            reference: $reference,
        );

        if (! $distPath) {
            try {
                $distPath = $fetchMirrorDistOnDemandAction->handle(
                    organization: $organization,
                    packageName: "{$vendor}/{$package}",
                    version: $version,
                    reference: $reference,
                );
            } catch (MirrorDistDownloadException) {
                return response()->json(['error' => 'The archive could not be downloaded from the upstream registry'], 502);
            } catch (LockTimeoutException) {
                return response()->json(['error' => 'The archive is still being downloaded, try again shortly'], 503);
            }
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
