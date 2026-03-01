<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitLabWebhookController extends Controller
{
    public function __invoke(Request $request, Repository $repository): JsonResponse
    {
        $event = $request->header('X-Gitlab-Event');

        return match ($event) {
            'Push Hook', 'Tag Push Hook' => $this->handleSyncEvent($repository),
            default => response()->json(['message' => 'Event ignored.'], 200),
        };
    }

    protected function handleSyncEvent(Repository $repository): JsonResponse
    {
        SyncRepositoryJob::dispatch($repository);

        return response()->json(['message' => 'Sync dispatched.']);
    }
}
