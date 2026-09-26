<?php

use App\Domains\Repository\Actions\BuildRepositoryHealthAction;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositorySyncLog;

it('limits recent syncs per repository and returns them newest first', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->for($organization)->create();

    foreach (range(1, 12) as $daysAgo) {
        RepositorySyncLog::factory()->for($repository)->successful()->create([
            'started_at' => now()->subDays($daysAgo),
        ]);
    }

    $health = app(BuildRepositoryHealthAction::class)->handle($organization)->sole();

    expect($health->recentSyncs)->toHaveCount(BuildRepositoryHealthAction::RECENT_SYNCS_LIMIT)
        ->and($health->recentSyncs[0]->startedAt->isSameDay(now()->subDay()))->toBeTrue()
        ->and($health->recentSyncs[0]->status)->toBe(SyncStatus::Success);
});

it('sorts failing repositories first', function () {
    $organization = Organization::factory()->create();
    Repository::factory()->for($organization)->create(['name' => 'a-healthy', 'sync_status' => RepositorySyncStatus::Ok]);
    Repository::factory()->for($organization)->create(['name' => 'b-failing', 'sync_status' => RepositorySyncStatus::Failed]);

    $names = app(BuildRepositoryHealthAction::class)->handle($organization)->pluck('name')->all();

    expect($names)->toBe(['b-failing', 'a-healthy']);
});

it('only includes repositories from the given organization', function () {
    $organization = Organization::factory()->create();
    Repository::factory()->for($organization)->create();
    Repository::factory()->create();

    expect(app(BuildRepositoryHealthAction::class)->handle($organization))->toHaveCount(1);
});
