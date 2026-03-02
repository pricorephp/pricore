<?php

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Events\RepositorySyncStatusUpdated;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('allows organization members to access the organization channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => 'owner',
    ]);

    expect($user->organizations()->where('organizations.uuid', $organization->uuid)->exists())->toBeTrue();
});

it('denies non-members from the organization channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->organizations()->where('organizations.uuid', $organization->uuid)->exists())->toBeFalse();
});

it('dispatches RepositorySyncStatusUpdated event during sync', function () {
    Event::fake([RepositorySyncStatusUpdated::class]);

    Http::fake([
        'api.github.com/repos/*/tags*' => Http::response([]),
        'api.github.com/repos/*/branches*' => Http::response([]),
        'api.github.com/repos/*' => Http::response([
            'name' => 'test-repo',
            'full_name' => 'owner/test-repo',
        ]),
    ]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()
        ->for($user, 'user')
        ->github()
        ->create();

    SyncRepositoryJob::dispatchSync($repository);

    Event::assertDispatched(RepositorySyncStatusUpdated::class, function (RepositorySyncStatusUpdated $event) use ($organization, $repository) {
        return $event->organizationUuid === $organization->uuid
            && $event->repositoryUuid === $repository->uuid;
    });

    Event::assertDispatched(RepositorySyncStatusUpdated::class, function (RepositorySyncStatusUpdated $event) {
        return $event->syncStatus === RepositorySyncStatus::Pending;
    });

    Event::assertDispatched(RepositorySyncStatusUpdated::class, function (RepositorySyncStatusUpdated $event) {
        return $event->syncStatus === RepositorySyncStatus::Ok;
    });
});

it('dispatches RepositorySyncStatusUpdated with failed status on sync failure', function () {
    Event::fake([RepositorySyncStatusUpdated::class]);

    Http::fake([
        'api.github.com/*' => Http::response([], 401),
    ]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()
        ->for($user, 'user')
        ->github()
        ->create();

    try {
        SyncRepositoryJob::dispatchSync($repository);
    } catch (Throwable) {
        // Expected to throw
    }

    Event::assertDispatched(RepositorySyncStatusUpdated::class, function (RepositorySyncStatusUpdated $event) {
        return $event->syncStatus === RepositorySyncStatus::Failed;
    });
});

it('broadcasts on the correct private channel', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->create([
        'organization_uuid' => $organization->uuid,
    ]);

    $event = new RepositorySyncStatusUpdated(
        $organization->uuid,
        $repository->uuid,
        RepositorySyncStatus::Ok,
        now()->toISOString(),
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-organization.{$organization->uuid}");
});

it('uses the correct broadcast event name', function () {
    $event = new RepositorySyncStatusUpdated(
        'org-uuid',
        'repo-uuid',
        RepositorySyncStatus::Pending,
        null,
    );

    expect($event->broadcastAs())->toBe('repository.sync.status-updated');
});
