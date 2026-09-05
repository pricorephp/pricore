<?php

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Actions\FindOrCreatePackageAction;
use App\Models\ActivityLog;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;

it('creates a new package when one does not exist', function () {
    $repository = Repository::factory()->create();
    $findOrCreatePackageAction = app(FindOrCreatePackageAction::class);

    $package = $findOrCreatePackageAction->handle($repository, 'vendor/new-package');

    expect($package)
        ->toBeInstanceOf(Package::class)
        ->name->toBe('vendor/new-package')
        ->organization_uuid->toBe($repository->organization_uuid)
        ->repository_uuid->toBe($repository->uuid)
        ->type->toBe('library')
        ->visibility->toBe('private');
});

it('returns existing package when one already exists', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $existingPackage = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create(['name' => 'vendor/existing-package']);

    $findOrCreatePackageAction = app(FindOrCreatePackageAction::class);

    $package = $findOrCreatePackageAction->handle($repository, 'vendor/existing-package');

    expect($package->uuid)->toBe($existingPackage->uuid);
    expect(Package::where('name', 'vendor/existing-package')->count())->toBe(1);
});

it('does not create a duplicate when called concurrently', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    // Simulate a race condition by pre-inserting the package
    Package::create([
        'organization_uuid' => $organization->uuid,
        'repository_uuid' => $repository->uuid,
        'name' => 'vendor/race-package',
        'type' => 'library',
        'visibility' => 'private',
    ]);

    $findOrCreatePackageAction = app(FindOrCreatePackageAction::class);

    // This should find the existing package instead of throwing a duplicate entry error
    $package = $findOrCreatePackageAction->handle($repository, 'vendor/race-package');

    expect($package->name)->toBe('vendor/race-package');
    expect(Package::where('name', 'vendor/race-package')->count())->toBe(1);
});

it('stores the source path of a newly discovered package and records the activity', function () {
    $repository = Repository::factory()->create();

    $package = app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing', 'packages/billing');

    expect($package?->source_path)->toBe('packages/billing')
        ->and(ActivityLog::query()->where('type', ActivityType::PackageCreated->value)->count())->toBe(1);
});

it('updates the source path when a package moved', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $existing = Package::factory()->forOrganization($organization)->forRepository($repository)
        ->atPath('src/billing')->create(['name' => 'vendor/billing']);

    $package = app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing', 'packages/billing');

    expect($package?->uuid)->toBe($existing->uuid)
        ->and($existing->fresh()?->source_path)->toBe('packages/billing')
        ->and(ActivityLog::count())->toBe(0);
});

it('clears the source path when a package moved back to the root', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    Package::factory()->forOrganization($organization)->forRepository($repository)
        ->atPath('packages/billing')->create(['name' => 'vendor/billing']);

    $package = app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing');

    expect($package?->fresh()?->source_path)->toBeNull();
});

it('refuses a name owned by another repository', function () {
    $organization = Organization::factory()->create();
    $owner = Repository::factory()->forOrganization($organization)->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    Package::factory()->forOrganization($organization)->forRepository($owner)->create(['name' => 'vendor/billing']);

    expect(app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing'))->toBeNull()
        ->and(Package::query()->where('name', 'vendor/billing')->sole()->repository_uuid)->toBe($owner->uuid);
});

it('refuses a name owned by a mirror', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $mirror = Mirror::factory()->create(['organization_uuid' => $organization->uuid]);
    Package::factory()->forOrganization($organization)->withoutRepository()
        ->create(['name' => 'vendor/billing', 'mirror_uuid' => $mirror->uuid]);

    expect(app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing'))->toBeNull();
});

it('adopts a package that has no source yet', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $orphan = Package::factory()->forOrganization($organization)->withoutRepository()->create(['name' => 'vendor/billing']);

    $package = app(FindOrCreatePackageAction::class)->handle($repository, 'vendor/billing', 'packages/billing');

    expect($package?->uuid)->toBe($orphan->uuid)
        ->and($orphan->fresh()?->repository_uuid)->toBe($repository->uuid)
        ->and($orphan->fresh()?->source_path)->toBe('packages/billing');
});
