<?php

namespace App\Domains\Release\Http\Controllers;

use App\Domains\Release\Actions\FetchLatestReleasesAction;
use Illuminate\Http\JsonResponse;

class ReleaseController
{
    public function __invoke(FetchLatestReleasesAction $fetchLatestReleasesAction): JsonResponse
    {
        return response()->json([
            'release_info' => $fetchLatestReleasesAction->handle(),
        ]);
    }
}
