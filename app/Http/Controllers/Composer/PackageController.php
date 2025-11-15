<?php

namespace App\Http\Controllers\Composer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $baseUrl = url("/{$organization->slug}/p2");

        return response()->json([
            'metadata-url' => "{$baseUrl}/%package%.json",
        ]);
    }
}
