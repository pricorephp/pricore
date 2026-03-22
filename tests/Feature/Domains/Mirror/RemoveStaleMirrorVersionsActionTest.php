<?php

use App\Domains\Mirror\Actions\RemoveStaleMirrorVersionsAction;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->create(['organization_uuid' => $this->organization->uuid]);
    $this->removeStaleMirrorVersionsAction = app(RemoveStaleMirrorVersionsAction::class);
});

it('removes versions that no longer exist upstream', function () {
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/package',
    ]);

    $keepVersion = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
    ]);

    $staleVersion = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '0.9.0',
    ]);

    $allPackageVersions = [
        'vendor/package' => [
            '1.0.0' => ['name' => 'vendor/package', 'version' => '1.0.0'],
        ],
    ];

    $removed = $this->removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

    expect($removed)->toBe(1);
    assertDatabaseHas('package_versions', ['uuid' => $keepVersion->uuid]);
    assertDatabaseMissing('package_versions', ['uuid' => $staleVersion->uuid]);
});

it('returns zero when mirror has no packages', function () {
    $allPackageVersions = [
        'vendor/package' => [
            '1.0.0' => ['name' => 'vendor/package'],
        ],
    ];

    $removed = $this->removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

    expect($removed)->toBe(0);
});

it('skips packages not present in upstream data', function () {
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/package',
    ]);

    $version = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
    ]);

    // Empty upstream data for this package
    $allPackageVersions = [];

    $removed = $this->removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

    expect($removed)->toBe(0);
    assertDatabaseHas('package_versions', ['uuid' => $version->uuid]);
});

it('handles multiple packages correctly', function () {
    $packageA = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/package-a',
    ]);

    $packageB = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/package-b',
    ]);

    PackageVersion::factory()->create(['package_uuid' => $packageA->uuid, 'version' => '1.0.0']);
    $staleA = PackageVersion::factory()->create(['package_uuid' => $packageA->uuid, 'version' => '0.1.0']);
    PackageVersion::factory()->create(['package_uuid' => $packageB->uuid, 'version' => '2.0.0']);
    $staleB = PackageVersion::factory()->create(['package_uuid' => $packageB->uuid, 'version' => '1.0.0']);

    $allPackageVersions = [
        'vendor/package-a' => [
            '1.0.0' => ['name' => 'vendor/package-a'],
        ],
        'vendor/package-b' => [
            '2.0.0' => ['name' => 'vendor/package-b'],
        ],
    ];

    $removed = $this->removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

    expect($removed)->toBe(2);
    assertDatabaseMissing('package_versions', ['uuid' => $staleA->uuid]);
    assertDatabaseMissing('package_versions', ['uuid' => $staleB->uuid]);
});

it('does not remove versions when all upstream versions match', function () {
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/package',
    ]);

    PackageVersion::factory()->create(['package_uuid' => $package->uuid, 'version' => '1.0.0']);
    PackageVersion::factory()->create(['package_uuid' => $package->uuid, 'version' => '2.0.0']);

    $allPackageVersions = [
        'vendor/package' => [
            '1.0.0' => ['name' => 'vendor/package'],
            '2.0.0' => ['name' => 'vendor/package'],
        ],
    ];

    $removed = $this->removeStaleMirrorVersionsAction->handle($this->mirror, $allPackageVersions);

    expect($removed)->toBe(0);
});
