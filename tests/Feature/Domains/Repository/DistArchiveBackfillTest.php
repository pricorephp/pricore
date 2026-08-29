<?php

use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $this->runBackfill = function () {
        $migration = require database_path('migrations/2026_08_29_000002_backfill_dist_archives.php');

        $migration->up();
    };
});

it('records an archive for every version that already has one', function () {
    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'dist_path' => 'acme/acme/test-package/1.0.0_aaaaaaaaaaaa.zip',
        'dist_shasum' => 'sha-one',
        'dist_size' => 512,
    ]);

    ($this->runBackfill)();

    $archive = DistArchive::query()->sole();

    expect($archive->package_version_uuid)->toBe($packageVersion->uuid)
        ->and($archive->package_uuid)->toBe($this->package->uuid)
        ->and($archive->source_reference)->toBe('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($archive->path)->toBe('acme/acme/test-package/1.0.0_aaaaaaaaaaaa.zip')
        ->and($archive->shasum)->toBe('sha-one')
        ->and($archive->size)->toBe(512)
        // Nothing becomes prunable on upgrade.
        ->and($archive->detached_at)->toBeNull();
});

it('tolerates versions missing a shasum or size', function () {
    PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'dist_path' => 'acme/one.zip',
        'dist_shasum' => null,
        'dist_size' => null,
    ]);

    ($this->runBackfill)();

    $archive = DistArchive::query()->sole();

    expect($archive->shasum)->toBeNull()
        ->and($archive->size)->toBeNull();
});

it('skips versions without an archive on disk', function () {
    PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'dist_path' => null,
    ]);

    PackageVersion::factory()->for($this->package)->create([
        'version' => '2.0.0',
        'source_reference' => null,
        'dist_path' => 'acme/orphan.zip',
    ]);

    ($this->runBackfill)();

    expect(DistArchive::query()->count())->toBe(0);
});

it('can be run again without duplicating rows', function () {
    PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'dist_path' => 'acme/one.zip',
        'dist_shasum' => 'sha-one',
        'dist_size' => 512,
    ]);

    ($this->runBackfill)();
    ($this->runBackfill)();

    expect(DistArchive::query()->count())->toBe(1);
});
