<?php

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Repository\Jobs\CompleteSyncBatchJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
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
