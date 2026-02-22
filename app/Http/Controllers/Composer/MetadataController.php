<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    /**
     * Return metadata for stable versions of a package.
     */
    public function show(Request $request, Organization $organization, string $vendor, string $packageName): JsonResponse
    {
        $packageName = "{$vendor}/{$packageName}";

        $package = $organization->packages()
            ->where('name', $packageName)
            ->first();

        if (! $package) {
            return response()->json([
                'packages' => [],
                'minified' => 'composer/2.0',
            ], 404);
        }

        $versions = $package->versions()
            ->stable()
            ->orderBy('released_at', 'desc')
            ->get();

        $versionsMetadata = $versions
            ->map(fn ($version) => VersionMetadataData::fromPackageVersion($version)->toArray())
            ->values()
            ->all();

        $minified = MetadataMinifier::minify($versionsMetadata);

        $response = response()
            ->json([
                'packages' => [
                    $package->name => $minified,
                ],
                'minified' => 'composer/2.0',
            ])
            ->setLastModified($package->updated_at)
            ->setPublic()
            ->setMaxAge(3600);

        $response->setEtag(md5((string) $response->getContent()));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * Return metadata for dev versions of a package.
     */
    public function showDev(Request $request, Organization $organization, string $vendor, string $packageName): JsonResponse
    {
        $packageName = "{$vendor}/{$packageName}";

        $package = $organization->packages()
            ->where('name', $packageName)
            ->first();

        if (! $package) {
            return response()->json([
                'packages' => [],
                'minified' => 'composer/2.0',
            ], 404);
        }

        $versions = $package->versions()
            ->dev()
            ->orderBy('released_at', 'desc')
            ->get();

        $versionsMetadata = $versions
            ->map(fn ($version) => VersionMetadataData::fromPackageVersion($version)->toArray())
            ->values()
            ->all();

        $minified = MetadataMinifier::minify($versionsMetadata);

        $response = response()
            ->json([
                'packages' => [
                    $package->name => $minified,
                ],
                'minified' => 'composer/2.0',
            ])
            ->setLastModified($package->updated_at)
            ->setPublic()
            ->setMaxAge(3600);

        $response->setEtag(md5((string) $response->getContent()));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
