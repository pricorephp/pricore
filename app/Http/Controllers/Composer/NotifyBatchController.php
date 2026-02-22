<?php

namespace App\Http\Controllers\Composer;

use App\Domains\Composer\Actions\RecordDownloadsAction;
use App\Domains\Composer\Contracts\Data\DownloadNotificationData;
use App\Domains\Composer\Requests\NotifyBatchRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Response;

class NotifyBatchController extends Controller
{
    public function __invoke(
        NotifyBatchRequest $request,
        Organization $organization,
        RecordDownloadsAction $recordDownloads,
    ): Response {
        /** @var array<int, array{name: string, version: string}> $validated */
        $validated = $request->validated('downloads');

        $downloads = collect($validated)
            ->map(fn (array $item) => new DownloadNotificationData(
                name: $item['name'],
                version: $item['version'],
            ))
            ->all();

        $recordDownloads->handle($organization, $downloads);

        return response()->noContent();
    }
}
