<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\ResolveBaseUrlFirstServedAction;
use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Domains\Mirror\Actions\ResolveOnDemandMirrorPackageAction;
use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

class MetadataController extends Controller
{
    private const int CACHE_TTL_HOURS = 24;

    private const int SETTLED_AFTER_SECONDS = 5;

    public function __construct(
        protected ResolveBaseUrlFirstServedAction $resolveBaseUrlFirstServedAction,
        protected ResolveOnDemandMirrorPackageAction $resolveOnDemandMirrorPackageAction,
    ) {}

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
            try {
                $package = $this->resolveOnDemandMirrorPackageAction->handle($organization, $packageName);
            } catch (MirrorSyncException) {
                return response()->json(['error' => 'The package could not be loaded from the upstream registry'], 502);
            } catch (LockTimeoutException) {
                return response()->json(['error' => 'The package is still being loaded, try again shortly'], 503);
            }
        } else {
            $this->resolveOnDemandMirrorPackageAction->refreshIfStale($package);
        }

        if (! $package) {
            return response()->json([
                'packages' => [],
                'minified' => 'composer/2.0',
            ], 404)
                ->setPrivate()
                ->setMaxAge(300);
        }

        $package->setRelation('organization', $organization);
        $package->loadMissing('mirror');

        $versionsQuery = fn (): HasMany => $scopeFilter($package->versions())
            // A subdirectory version installs from its dist only (no source block),
            // so one without an archive yet would be offered but fail to install
            ->where(fn (Builder $query) => $query->whereNull('source_path')->orWhereNotNull('dist_url'));

        /** @var object{updated_at: string|null, count: int|string} $versionsState */
        $versionsState = $versionsQuery()->toBase()
            ->selectRaw('max(updated_at) as updated_at, count(*) as count')
            ->first();

        $versionsUpdatedAt = $versionsState->updated_at ? Date::parse($versionsState->updated_at) : null;

        // The package is touched when a version is deleted, which the remaining
        // versions' timestamps would not reflect. The dist URLs follow the base
        // URL, which no stored timestamp reflects either.
        $baseUrlFirstServedAt = $this->resolveBaseUrlFirstServedAction->handle();

        $lastModified = collect([$versionsUpdatedAt, $package->updated_at, $baseUrlFirstServedAt])
            ->filter()
            ->max() ?? $baseUrlFirstServedAt;

        // Composer revalidates with If-Modified-Since only, which needs no body.
        $ifModifiedSince = $request->headers->getDate('If-Modified-Since');

        if ($ifModifiedSince && ! $request->headers->has('If-None-Match') && $ifModifiedSince->getTimestamp() >= $lastModified->getTimestamp()) {
            return $this->withCacheHeaders(new JsonResponse, $lastModified, $maxAge)->setNotModified();
        }

        // Everything the body is built from, so a change is served under a new
        // key instead of every write path having to forget the old one.
        $cacheKey = 'composer:metadata:'.sha1(implode('|', [
            $package->uuid,
            $package->updated_at?->getTimestamp(),
            $maxAge,
            $versionsState->count,
            $versionsState->updated_at,
            $organization->slug,
            url('/'),
            $package->mirror?->updated_at?->getTimestamp(),
            (int) config('pricore.dist.enabled'),
        ]));

        $content = Cache::get($cacheKey);

        if (! is_string($content)) {
            $versionsMetadata = array_values(
                $versionsQuery()
                    ->chaperone()
                    ->orderBy('released_at', 'desc')
                    ->get()
                    ->map(fn (PackageVersion $version) => VersionMetadataData::fromPackageVersion($version)->toArray())
                    ->all()
            );

            $content = (string) response()
                ->json([
                    'packages' => [
                        $packageName => MetadataMinifier::minify($versionsMetadata),
                    ],
                    'minified' => 'composer/2.0',
                ])
                ->getContent();

            // Timestamps have second precision: a write later in the same second
            // would not change the key, so only settled data is cached.
            $lastChangedAt = collect([$versionsUpdatedAt, $package->updated_at, $package->mirror?->updated_at])
                ->filter()
                ->max();

            if (! $lastChangedAt || $lastChangedAt->diffInSeconds(now()) >= self::SETTLED_AFTER_SECONDS) {
                Cache::put($cacheKey, $content, now()->addHours(self::CACHE_TTL_HOURS));
            }
        }

        $response = $this->withCacheHeaders(JsonResponse::fromJsonString($content), $lastModified, $maxAge)
            ->setEtag(md5($content));

        $response->isNotModified($request);

        return $response;
    }

    private function withCacheHeaders(JsonResponse $response, \DateTimeInterface $lastModified, int $maxAge): JsonResponse
    {
        return $response
            ->setLastModified($lastModified)
            ->setPrivate()
            ->setMaxAge($maxAge);
    }
}
