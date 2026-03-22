<?php

use App\Domains\Mirror\Actions\FindOrCreateMirrorPackageAction;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->create(['organization_uuid' => $this->organization->uuid]);
    $this->findOrCreateMirrorPackageAction = app(FindOrCreateMirrorPackageAction::class);
});

it('creates a new package when it does not exist', function () {
    $package = $this->findOrCreateMirrorPackageAction->handle($this->mirror, 'vendor/new-package');

    expect($package->name)->toBe('vendor/new-package');
    expect($package->organization_uuid)->toBe($this->organization->uuid);
    expect($package->mirror_uuid)->toBe($this->mirror->uuid);
    expect($package->type)->toBe('library');
    expect($package->visibility)->toBe('private');

    assertDatabaseHas('packages', [
        'name' => 'vendor/new-package',
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
    ]);
});

it('returns existing package when it already exists', function () {
    $existing = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'name' => 'vendor/existing-package',
        'mirror_uuid' => $this->mirror->uuid,
    ]);

    $package = $this->findOrCreateMirrorPackageAction->handle($this->mirror, 'vendor/existing-package');

    expect($package->uuid)->toBe($existing->uuid);
    expect(Package::where('name', 'vendor/existing-package')->count())->toBe(1);
});
