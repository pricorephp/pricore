<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;

class GenericGitWebhookController extends Controller
{
    public function __invoke(Repository $repository): JsonResponse
    {
        SyncRepositoryJob::dispatch($repository);

        return response()->json(['message' => 'Sync dispatched.']);
    }
}
