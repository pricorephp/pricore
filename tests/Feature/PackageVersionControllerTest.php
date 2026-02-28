<?php

use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can delete a version as an owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    actingAs($user)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version->uuid]))
        ->assertRedirect();

    assertDatabaseMissing('package_versions', ['uuid' => $version->uuid]);
});

it('can delete a version as an admin', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $admin = User::factory()->create();
    $organization->members()->attach($admin->uuid, ['role' => 'admin', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    actingAs($admin)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version->uuid]))
        ->assertRedirect();

    assertDatabaseMissing('package_versions', ['uuid' => $version->uuid]);
});

it('cannot delete a version as a member', function () {
    $this->withoutVite();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $member = User::factory()->create();
    $organization->members()->attach($member->uuid, ['role' => 'member', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    actingAs($member)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version->uuid]))
        ->assertForbidden();

    assertDatabaseHas('package_versions', ['uuid' => $version->uuid]);
});

it('cannot delete a version from another organization', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $otherUser = User::factory()->create();
    $otherOrganization = Organization::factory()->create(['owner_uuid' => $otherUser->uuid]);

    $repository = Repository::factory()->forOrganization($otherOrganization)->create();
    $package = Package::factory()->forOrganization($otherOrganization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    actingAs($user)
        ->delete(route('organizations.packages.versions.destroy', [$otherOrganization->slug, $package->uuid, $version->uuid]))
        ->assertForbidden();

    assertDatabaseHas('package_versions', ['uuid' => $version->uuid]);
});

it('returns 404 when version does not belong to package', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $otherPackage = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($otherPackage)->create();

    actingAs($user)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version->uuid]))
        ->assertNotFound();

    assertDatabaseHas('package_versions', ['uuid' => $version->uuid]);
});

it('returns 404 when package does not belong to organization', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $otherOrganization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($otherOrganization)->create();
    $package = Package::factory()->forOrganization($otherOrganization)->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    actingAs($user)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version->uuid]))
        ->assertNotFound();
});

it('preserves other versions when deleting one', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();
    $version1 = PackageVersion::factory()->forPackage($package)->create(['version' => '1.0.0']);
    $version2 = PackageVersion::factory()->forPackage($package)->create(['version' => '2.0.0']);

    actingAs($user)
        ->delete(route('organizations.packages.versions.destroy', [$organization->slug, $package->uuid, $version1->uuid]));

    assertDatabaseMissing('package_versions', ['uuid' => $version1->uuid]);
    assertDatabaseHas('package_versions', ['uuid' => $version2->uuid]);
});
