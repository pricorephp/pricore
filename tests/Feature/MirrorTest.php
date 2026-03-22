<?php

use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('can view mirrors page as an owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    Mirror::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->get(route('organizations.settings.mirrors.index', $organization->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/settings/mirrors')
            ->has('mirrors', 1)
            ->has('organization')
        );
});

it('cannot view mirrors page as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    actingAs($member)
        ->get(route('organizations.settings.mirrors.index', $organization->slug))
        ->assertForbidden();
});

it('can create a mirror as an owner', function () {
    Queue::fake(SyncMirrorJob::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Test Mirror',
            'url' => 'https://satis.example.com',
            'auth_type' => 'none',
            'mirror_dist' => true,
        ])
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    assertDatabaseHas('mirrors', [
        'organization_uuid' => $organization->uuid,
        'name' => 'Test Mirror',
        'url' => 'https://satis.example.com',
    ]);

    Queue::assertPushed(SyncMirrorJob::class);
});

it('can create a mirror with basic auth', function () {
    Queue::fake(SyncMirrorJob::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Private Mirror',
            'url' => 'https://private.example.com',
            'auth_type' => 'basic',
            'username' => 'user',
            'password' => 'secret',
            'mirror_dist' => true,
        ])
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    $mirror = Mirror::where('name', 'Private Mirror')->firstOrFail();
    expect($mirror->auth_type->value)->toBe('basic');
    expect($mirror->auth_credentials)->toBe([
        'username' => 'user',
        'password' => 'secret',
    ]);
});

it('can create a mirror with mirror_dist disabled', function () {
    Queue::fake(SyncMirrorJob::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'No Dist Mirror',
            'url' => 'https://satis.example.com',
            'auth_type' => 'none',
            'mirror_dist' => false,
        ])
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    $mirror = Mirror::where('name', 'No Dist Mirror')->firstOrFail();
    expect($mirror->mirror_dist)->toBeFalse();
});

it('cannot create a mirror as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    actingAs($member)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Test Mirror',
            'url' => 'https://satis.example.com',
            'auth_type' => 'none',
        ])
        ->assertForbidden();
});

it('validates required fields when creating a mirror', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [])
        ->assertSessionHasErrors(['name', 'url', 'auth_type']);
});

it('validates basic auth fields are required when auth_type is basic', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Test',
            'url' => 'https://example.com',
            'auth_type' => 'basic',
        ])
        ->assertSessionHasErrors(['username', 'password']);
});

it('can delete a mirror as an owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->delete(route('organizations.settings.mirrors.destroy', [$organization->slug, $mirror->uuid]))
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    assertDatabaseMissing('mirrors', ['uuid' => $mirror->uuid]);
});

it('can delete a mirror as an admin', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $admin = User::factory()->create();
    $organization->members()->attach($admin->uuid, ['role' => 'admin', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($admin)
        ->delete(route('organizations.settings.mirrors.destroy', [$organization->slug, $mirror->uuid]))
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    assertDatabaseMissing('mirrors', ['uuid' => $mirror->uuid]);
});

it('cannot delete a mirror as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($member)
        ->delete(route('organizations.settings.mirrors.destroy', [$organization->slug, $mirror->uuid]))
        ->assertForbidden();

    assertDatabaseHas('mirrors', ['uuid' => $mirror->uuid]);
});

it('deletes sync logs when mirror is deleted', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);
    $mirror->syncLogs()->create([
        'status' => 'success',
        'started_at' => now(),
    ]);

    actingAs($user)
        ->delete(route('organizations.settings.mirrors.destroy', [$organization->slug, $mirror->uuid]));

    assertDatabaseMissing('mirror_sync_logs', ['mirror_uuid' => $mirror->uuid]);
});

it('nullifies mirror_uuid on packages when mirror is deleted', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $organization->uuid,
        'mirror_uuid' => $mirror->uuid,
    ]);

    actingAs($user)
        ->delete(route('organizations.settings.mirrors.destroy', [$organization->slug, $mirror->uuid]));

    assertDatabaseMissing('mirrors', ['uuid' => $mirror->uuid]);
    assertDatabaseHas('packages', [
        'uuid' => $package->uuid,
        'mirror_uuid' => null,
    ]);
});

it('can trigger a mirror sync as an owner', function () {
    Queue::fake(SyncMirrorJob::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->synced()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.sync', [$organization->slug, $mirror->uuid]))
        ->assertRedirect();

    $mirror->refresh();
    expect($mirror->sync_status->value)->toBe('pending');

    Queue::assertPushed(SyncMirrorJob::class);
});

it('cannot trigger a mirror sync as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    $mirror = Mirror::factory()->synced()->create(['organization_uuid' => $organization->uuid]);

    actingAs($member)
        ->post(route('organizations.settings.mirrors.sync', [$organization->slug, $mirror->uuid]))
        ->assertForbidden();
});
