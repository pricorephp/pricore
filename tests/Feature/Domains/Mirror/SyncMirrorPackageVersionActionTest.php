<?php

use App\Domains\Mirror\Actions\SyncMirrorPackageVersionAction;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->create(['organization_uuid' => $this->organization->uuid]);
    $this->package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
    ]);
    $this->syncMirrorPackageVersionAction = app(SyncMirrorPackageVersionAction::class);
});

it('creates a new package version and returns added', function () {
    $composerJson = [
        'name' => $this->package->name,
        'version' => '1.0.0',
        'dist' => [
            'url' => 'https://example.com/dist/1.0.0.zip',
            'reference' => 'abc123',
        ],
    ];

    $result = $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '1.0.0',
        $composerJson,
    );

    expect($result)->toBe('added');

    assertDatabaseHas('package_versions', [
        'package_uuid' => $this->package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc123',
    ]);
});

it('skips a version when reference has not changed', function () {
    PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc123',
    ]);

    $composerJson = [
        'name' => $this->package->name,
        'version' => '1.0.0',
        'dist' => [
            'reference' => 'abc123',
        ],
    ];

    $result = $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '1.0.0',
        $composerJson,
    );

    expect($result)->toBe('skipped');
});

it('updates a version when reference has changed', function () {
    PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc123',
    ]);

    $composerJson = [
        'name' => $this->package->name,
        'version' => '1.0.0',
        'dist' => [
            'reference' => 'def456',
        ],
    ];

    $result = $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '1.0.0',
        $composerJson,
    );

    expect($result)->toBe('updated');

    assertDatabaseHas('package_versions', [
        'package_uuid' => $this->package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'def456',
    ]);
});

it('extracts reference from source when dist reference is missing', function () {
    $composerJson = [
        'name' => $this->package->name,
        'version' => '2.0.0',
        'source' => [
            'url' => 'https://github.com/example/repo.git',
            'reference' => 'source-ref-123',
        ],
    ];

    $result = $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '2.0.0',
        $composerJson,
    );

    expect($result)->toBe('added');

    assertDatabaseHas('package_versions', [
        'package_uuid' => $this->package->uuid,
        'version' => '2.0.0',
        'source_reference' => 'source-ref-123',
        'source_url' => 'https://github.com/example/repo.git',
    ]);
});

it('generates a hash reference when no dist or source reference exists', function () {
    $composerJson = [
        'name' => $this->package->name,
        'version' => '3.0.0',
    ];

    $result = $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '3.0.0',
        $composerJson,
    );

    expect($result)->toBe('added');

    $version = PackageVersion::where('package_uuid', $this->package->uuid)
        ->where('version', '3.0.0')
        ->firstOrFail();

    expect($version->source_reference)->toHaveLength(64); // sha256 hash
});

it('normalizes the version string', function () {
    $composerJson = [
        'name' => $this->package->name,
        'version' => '1.2.3',
        'dist' => ['reference' => 'ref'],
    ];

    $this->syncMirrorPackageVersionAction->handle(
        $this->package,
        '1.2.3',
        $composerJson,
    );

    assertDatabaseHas('package_versions', [
        'package_uuid' => $this->package->uuid,
        'version' => '1.2.3',
        'normalized_version' => '1.2.3.0',
    ]);
});
