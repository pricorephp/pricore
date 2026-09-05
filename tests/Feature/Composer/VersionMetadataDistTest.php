<?php

use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

it('includes dist info with shasum when dist_url is set', function () {
    $organization = Organization::factory()->create();
    $package = Package::factory()->for($organization, 'organization')->create(['name' => 'acme/test-package']);
    $version = PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_url' => 'git@github.com:acme/test-package.git',
        'source_reference' => 'abc123',
        'dist_url' => 'https://example.com/dists/acme/test-package/1.0.0/abc123.zip',
        'dist_shasum' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
    ]);

    $metadata = VersionMetadataData::fromPackageVersion($version);
    $array = $metadata->toArray();

    expect($array['dist'])->toBe([
        'type' => 'zip',
        'url' => 'https://example.com/dists/acme/test-package/1.0.0/abc123.zip',
        'reference' => 'abc123',
        'shasum' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
    ]);
});

it('excludes dist info when dist_url is null', function () {
    $organization = Organization::factory()->create();
    $package = Package::factory()->for($organization, 'organization')->create(['name' => 'acme/test-package']);
    $version = PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_url' => 'git@github.com:acme/test-package.git',
        'source_reference' => 'abc123',
        'dist_url' => null,
        'dist_shasum' => null,
    ]);

    $metadata = VersionMetadataData::fromPackageVersion($version);
    $array = $metadata->toArray();

    expect($array)->not->toHaveKey('dist');
});

it('omits the source block for versions read from a subdirectory', function () {
    $organization = Organization::factory()->create();
    $package = Package::factory()->for($organization, 'organization')->atPath('packages/billing')->create(['name' => 'acme/billing']);
    $version = PackageVersion::factory()->for($package)->atPath('packages/billing')->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_url' => 'git@github.com:acme/monorepo.git',
        'source_reference' => 'abc123',
        'dist_url' => 'https://example.com/dists/acme/billing/1.0.0/abc123.zip',
        'dist_shasum' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
    ]);

    $array = VersionMetadataData::fromPackageVersion($version)->toArray();

    expect($array)->not->toHaveKey('source')
        ->and($array['dist']['reference'])->toBe('abc123');
});

it('keeps the source block for versions read from the repository root', function () {
    $organization = Organization::factory()->create();
    $package = Package::factory()->for($organization, 'organization')->create(['name' => 'acme/test-package']);
    $version = PackageVersion::factory()->for($package)->create([
        'source_url' => 'git@github.com:acme/test-package.git',
        'source_reference' => 'abc123',
    ]);

    expect(VersionMetadataData::fromPackageVersion($version)->toArray()['source'])->toBe([
        'type' => 'git',
        'url' => 'git@github.com:acme/test-package.git',
        'reference' => 'abc123',
        'shasum' => null,
    ]);
});
