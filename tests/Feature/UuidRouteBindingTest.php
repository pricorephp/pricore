<?php

use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

uses()->group('routing');

/**
 * Postgres types these columns as `uuid` and rejects malformed input at the driver,
 * which surfaces as a 500 and poisons the surrounding transaction. SQLite and MySQL
 * compare them as strings and quietly match nothing, so this is asserted directly on
 * the binding rather than through a request — a request-level test would pass on
 * SQLite whether or not the guard exists.
 */
it('refuses to query the database for a malformed uuid route value', function () {
    $repository = new Repository;

    expect(fn () => $repository->resolveRouteBindingQuery(
        $repository->newQuery(),
        'non-existent-uuid',
        'uuid',
    ))->toThrow(ModelNotFoundException::class);
});

it('refuses a malformed value bound on the default route key', function () {
    $repository = new Repository;

    expect(fn () => $repository->resolveRouteBindingQuery(
        $repository->newQuery(),
        'not-a-uuid',
    ))->toThrow(ModelNotFoundException::class);
});

it('still resolves a well formed uuid', function () {
    $repository = Repository::factory()->create();

    $resolved = $repository->resolveRouteBindingQuery(
        Repository::query(),
        $repository->uuid,
        'uuid',
    )->first();

    expect($resolved?->uuid)->toBe($repository->uuid);
});

it('leaves bindings on non-uuid keys alone', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);

    $resolved = $organization->resolveRouteBindingQuery(
        Organization::query(),
        'acme',
        'slug',
    )->first();

    expect($resolved?->uuid)->toBe($organization->uuid);
});

it('returns 404 rather than erroring for a malformed uuid in a url', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => 'owner',
    ]);

    $this->actingAs($user)
        ->get("/organizations/{$organization->slug}/repositories/not-a-uuid")
        ->assertNotFound();
});
