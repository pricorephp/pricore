<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BitbucketWebhookController extends Controller
{
    public function __invoke(Request $request, Repository $repository): JsonResponse
    {
        $event = $request->header('X-Event-Key');

        return match ($event) {
            'repo:push', 'repo:refs_changed' => $this->handleSyncEvent($repository),
            default => response()->json(['message' => 'Event ignored.'], 200),
        };
    }

    protected function handleSyncEvent(Repository $repository): JsonResponse
    {
        SyncRepositoryJob::dispatch($repository);

        return response()->json(['message' => 'Sync dispatched.']);
    }
}
