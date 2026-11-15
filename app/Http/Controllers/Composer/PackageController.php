<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\ResolveBaseUrlFirstServedAction;
use App\Domains\Composer\Actions\ResolveOnDemandMirrorsToggledAtAction;
use App\Domains\Mirror\Contracts\Enums\MirrorSyncMode;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        ResolveBaseUrlFirstServedAction $resolveBaseUrlFirstServedAction,
        ResolveOnDemandMirrorsToggledAtAction $resolveOnDemandMirrorsToggledAtAction,
    ): JsonResponse {
        $baseUrl = url("/{$organization->slug}/p2");
        $notifyBatchUrl = url("/{$organization->slug}/notify-batch");

        $availablePackages = $organization->packages()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $rootMetadata = [
            'metadata-url' => "{$baseUrl}/%package%.json",
            'available-packages' => $availablePackages,
            'notify-batch' => $notifyBatchUrl,
            'security-advisories' => [
                'api-url' => route('composer.security-advisories', ['organization' => $organization->slug]),
                'metadata' => false,
            ],
        ];

        $onDemandMirrorAddedAt = $organization->mirrors()
            ->where('sync_mode', MirrorSyncMode::OnDemand)
            ->max('created_at');

        // Composer only asks for listed packages, so with an on-demand mirror
        // the list is left out and every dependency is requested here.
        if ($onDemandMirrorAddedAt && MirrorSyncMode::onDemandEnabled()) {
            unset($rootMetadata['available-packages']);
        }

        $response = response()->json($rootMetadata);

        $packagesUpdatedAt = $organization->packages()->max('updated_at');

        // The metadata URLs follow the base URL, which no package timestamp
        // reflects; neither does dropping the package list for a new mirror.
        $lastModified = collect([
            $packagesUpdatedAt ? new \DateTimeImmutable($packagesUpdatedAt) : null,
            $onDemandMirrorAddedAt ? new \DateTimeImmutable($onDemandMirrorAddedAt) : null,
            $onDemandMirrorAddedAt ? $resolveOnDemandMirrorsToggledAtAction->handle() : null,
            $resolveBaseUrlFirstServedAction->handle(),
        ])->filter()->max();

        $response->setLastModified($lastModified);

        $response->setPrivate()
            ->setMaxAge(300);

        $response->setEtag(md5((string) $response->getContent()));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
