<?php

use App\Domains\Repository\Actions\CollectRefsAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Actions\CreateSyncLogAction;
use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\PruneEmptyRefsAction;
use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.github.com/repos/*/tags*' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
        ]),
        'api.github.com/repos/*/branches*' => Http::response([]),
        'api.github.com/repos/*' => Http::response(['name' => 'test-repo', 'full_name' => 'owner/test-repo']),
    ]);

    $user = User::factory()->create();
    $this->repository = Repository::factory()
        ->for(Organization::factory(), 'organization')
        ->github()
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()->for($user, 'user')->github()->create();

    $this->startBatch = function (array $attributes = []): string {
        $batch = app(BatchRepository::class)->store(Bus::batch([]));

        RepositorySyncLog::factory()->for($this->repository)->pending()->create([
            'batch_id' => $batch->id,
            'started_at' => now()->subMinute(),
            ...$attributes,
        ]);

        return $batch->id;
    };

    $this->runJob = fn (bool $force = false) => (new SyncRepositoryJob($this->repository, force: $force))->handle(
        app(CreateSyncLogAction::class),
        app(CollectRefsAction::class),
        app(CreateGitCloneAction::class),
        app(FilterChangedRefsAction::class),
        app(RemoveStaleVersionsAction::class),
        app(PruneEmptyRefsAction::class),
    );
});

it('defers a sync while a batch of an earlier one still runs', function () {
    Bus::fake();
    ($this->startBatch)();

    ($this->runJob)();

    Bus::assertNothingBatched();
    Http::assertNothingSent();

    expect($this->repository->fresh()?->sync_requested_at)->not->toBeNull()
        ->and($this->repository->syncLogs()->count())->toBe(1);
});

it('records a deferred forced sync as a full sync request', function () {
    Bus::fake();
    ($this->startBatch)();

    ($this->runJob)(true);

    $repository = $this->repository->fresh();

    expect($repository?->full_sync_requested_at)->not->toBeNull()
        ->and($repository?->sync_requested_at)->toBeNull();
});

it('syncs once the earlier batch has finished and clears the request', function () {
    Bus::fake();
    $batchId = ($this->startBatch)();
    app(BatchRepository::class)->markAsFinished($batchId);

    $this->repository->update(['sync_requested_at' => now()->subMinute()]);

    ($this->runJob)();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);

    expect($this->repository->fresh()?->sync_requested_at)->toBeNull();
});

it('does not wait on a batch that has run for too long', function () {
    Bus::fake();
    ($this->startBatch)(['started_at' => now()->subHours(SyncRepositoryJob::RUNNING_BATCH_HOURS + 1)]);

    ($this->runJob)();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
});
