<?php

use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can delete a repository as an owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->delete(route('organizations.repositories.destroy', [$organization->slug, $repository->uuid]))
        ->assertRedirect(route('organizations.repositories.index', $organization->slug));

    assertDatabaseMissing('repositories', ['uuid' => $repository->uuid]);
});

it('can access edit repository page as an owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->get(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/repositories/edit')
            ->has('repository')
            ->has('organization')
        );
});

it('cannot access edit repository page as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($member)
        ->get(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->assertForbidden();
});

it('cannot delete a repository as a member', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($member)
        ->delete(route('organizations.repositories.destroy', [$organization->slug, $repository->uuid]))
        ->assertForbidden();

    assertDatabaseHas('repositories', ['uuid' => $repository->uuid]);
});

it('can delete a repository as an admin', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $admin = User::factory()->create();
    $organization->members()->attach($admin->uuid, ['role' => 'admin', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($admin)
        ->delete(route('organizations.repositories.destroy', [$organization->slug, $repository->uuid]))
        ->assertRedirect(route('organizations.repositories.index', $organization->slug));

    assertDatabaseMissing('repositories', ['uuid' => $repository->uuid]);
});

it('cannot delete a repository from another organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $otherUser = User::factory()->create();
    $otherOrganization = Organization::factory()->create(['owner_uuid' => $otherUser->uuid]);
    $otherOrganization->members()->attach($otherUser->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $otherOrganization->uuid]);

    actingAs($user)
        ->delete(route('organizations.repositories.destroy', [$otherOrganization->slug, $repository->uuid]))
        ->assertForbidden();

    assertDatabaseHas('repositories', ['uuid' => $repository->uuid]);
});

it('deletes sync logs when repository is deleted', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    $syncLog = \App\Models\RepositorySyncLog::factory()->create(['repository_uuid' => $repository->uuid]);

    actingAs($user)
        ->delete(route('organizations.repositories.destroy', [$organization->slug, $repository->uuid]));

    assertDatabaseMissing('repository_sync_logs', ['uuid' => $syncLog->uuid]);
});

it('deletes packages when repository is deleted', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->create(['organization_uuid' => $organization->uuid]);

    $package = Package::factory()->create([
        'organization_uuid' => $organization->uuid,
        'repository_uuid' => $repository->uuid,
    ]);

    actingAs($user)
        ->delete(route('organizations.repositories.destroy', [$organization->slug, $repository->uuid]));

    assertDatabaseMissing('packages', ['uuid' => $package->uuid]);
});
