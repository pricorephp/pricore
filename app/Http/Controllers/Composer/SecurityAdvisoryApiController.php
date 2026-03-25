<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Security\Contracts\Data\ComposerAdvisoryData;
use App\Http\Controllers\Controller;
use App\Models\SecurityAdvisory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityAdvisoryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packageNames = $request->input('packages', []);

        if (empty($packageNames)) {
            return response()->json(['advisories' => []]);
        }

        $advisories = SecurityAdvisory::query()
            ->whereIn('package_name', $packageNames)
            ->get()
            ->map(fn (SecurityAdvisory $advisory) => ComposerAdvisoryData::fromModel($advisory))
            ->groupBy('packageName')
            ->map(fn ($group) => $group->toArray());

        return response()->json(['advisories' => $advisories]);
    }
}
