<?php

use App\Domains\Repository\Actions\FindOrCreatePackageAction;
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
