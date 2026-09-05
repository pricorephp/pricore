<?php

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Repository\Actions\CleanupGitCloneAction;
use App\Domains\Repository\Jobs\CompleteSyncBatchJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

it('skips recording activity when the organization was deleted during a repository sync', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->for($organization)->create();
    $syncLog = RepositorySyncLog::factory()
        ->for($repository)
        ->successful()
        ->create(['versions_added' => 1]);

    $organization->delete();
    $repository->unsetRelation('organization');

    Log::spy();

    $recordActivityTask = Mockery::mock(RecordActivityTask::class);
    $recordActivityTask->shouldNotReceive('handle');

    $job = new CompleteSyncBatchJob($syncLog->uuid, $repository->uuid, null, 'batch-id');
    $recordActivity = new ReflectionMethod($job, 'recordActivity');

    $recordActivity->invoke($job, $repository, $syncLog, $recordActivityTask);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Skipping repository sync activity because organization is unavailable', [
            'repository_uuid' => $repository->uuid,
            'organization_uuid' => $organization->uuid,
            'sync_log_uuid' => $syncLog->uuid,
        ]);
});

it('adds versions removed per ref to the stale version count', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->for($organization)->create();
    $syncLog = RepositorySyncLog::factory()->for($repository)->pending()->create(['versions_removed' => 2]);

    $batch = Bus::batch([])->dispatch();
    Cache::put("sync-batch:{$batch->id}:added", 1);
    Cache::put("sync-batch:{$batch->id}:removed", 3);

    (new CompleteSyncBatchJob($syncLog->uuid, $repository->uuid, null, $batch->id))
        ->handle(app(CleanupGitCloneAction::class), app(RecordActivityTask::class));

    expect($syncLog->fresh()?->versions_removed)->toBe(5)
        ->and($syncLog->fresh()?->versions_added)->toBe(1)
        ->and(Cache::get("sync-batch:{$batch->id}:removed"))->toBeNull();
});
