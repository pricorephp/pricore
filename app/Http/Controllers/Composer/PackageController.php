<?php

namespace App\Http\Controllers\Composer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
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
        ]);

        $lastModified = $organization->packages()->max('updated_at');

        if ($lastModified) {
            $response->setLastModified(new \DateTime($lastModified));
        }

        $response->setPrivate()
            ->setMaxAge(300);

        $response->setEtag(md5((string) $response->getContent()));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
