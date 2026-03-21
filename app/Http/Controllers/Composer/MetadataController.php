<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    /**
     * Return metadata for stable versions of a package.
     */
    public function show(Request $request, Organization $organization, string $vendor, string $packageName): JsonResponse
    {
        return $this->metadataResponse(
            request: $request,
            organization: $organization,
            packageName: "{$vendor}/{$packageName}",
            scopeFilter: fn (HasMany $query) => $query->stable(),
            maxAge: 3600,
        );
    }

    /**
     * Return metadata for dev versions of a package.
     */
    public function showDev(Request $request, Organization $organization, string $vendor, string $packageName): JsonResponse
    {
        return $this->metadataResponse(
            request: $request,
            organization: $organization,
            packageName: "{$vendor}/{$packageName}",
            scopeFilter: fn (HasMany $query) => $query->dev(),
            maxAge: 300,
        );
    }

    /**
     * @param  \Closure(HasMany<PackageVersion, Package>): HasMany<PackageVersion, Package>  $scopeFilter
     */
    private function metadataResponse(
        Request $request,
        Organization $organization,
        string $packageName,
        \Closure $scopeFilter,
        int $maxAge,
    ): JsonResponse {
        $package = $organization->packages()
            ->where('name', $packageName)
            ->first();

        if (! $package) {
            return response()->json([
                'packages' => [],
                'minified' => 'composer/2.0',
            ], 404)
                ->setPrivate()
                ->setMaxAge(300);
        }

        $versions = $scopeFilter($package->versions())
            ->orderBy('released_at', 'desc')
            ->get();

        $versionsMetadata = $versions
            ->map(fn ($version) => VersionMetadataData::fromPackageVersion($version)->toArray())
            ->values()
            ->all();

        $minified = MetadataMinifier::minify($versionsMetadata);

        $lastModified = $versions->max('updated_at') ?? $package->updated_at;

        $response = response()
            ->json([
                'packages' => [
                    $packageName => $minified,
                ],
                'minified' => 'composer/2.0',
            ])
            ->setLastModified($lastModified)
            ->setPrivate()
            ->setMaxAge($maxAge);

        $response->setEtag(md5((string) $response->getContent()));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
