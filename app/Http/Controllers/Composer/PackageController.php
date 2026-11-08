<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\ResolveBaseUrlFirstServedAction;
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
    ): JsonResponse {
        $baseUrl = url("/{$organization->slug}/p2");
        $notifyBatchUrl = url("/{$organization->slug}/notify-batch");

        $availablePackages = $organization->packages()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $response = response()->json([
            'metadata-url' => "{$baseUrl}/%package%.json",
            'available-packages' => $availablePackages,
            'notify-batch' => $notifyBatchUrl,
            'security-advisories' => [
                'api-url' => route('composer.security-advisories', ['organization' => $organization->slug]),
                'metadata' => false,
            ],
        ]);

        $packagesUpdatedAt = $organization->packages()->max('updated_at');

        // The metadata URLs follow the base URL, which no package timestamp reflects.
        $lastModified = collect([
            $packagesUpdatedAt ? new \DateTimeImmutable($packagesUpdatedAt) : null,
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
