<?php

use App\Models\Package;
use App\Models\PackageVersion;

it('sorts semantic versions before dev versions', function () {
    $package = Package::factory()->create();

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-main',
        'normalized_version' => 'dev-main',
        'released_at' => now()->subDays(1),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'released_at' => now()->subDays(5),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
        'released_at' => now()->subDays(3),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-fix-something',
        'normalized_version' => 'dev-fix-something',
        'released_at' => now(),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => '1.5.0',
        'normalized_version' => '1.5.0.0',
        'released_at' => now()->subDays(2),
    ]);

    $versions = $package->versions()
        ->orderBySemanticVersion('desc')
        ->pluck('version')
        ->toArray();

    // Semantic versions should come first (highest to lowest), then dev versions
    expect($versions[0])->toBe('2.0.0');
    expect($versions[1])->toBe('1.5.0');
    expect($versions[2])->toBe('1.0.0');

    // Dev versions should come after semantic versions
    expect(array_slice($versions, 3))->each->toStartWith('dev-');
});

it('sorts dev versions by released_at when after semantic versions', function () {
    $package = Package::factory()->create();

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-old-branch',
        'normalized_version' => 'dev-old-branch',
        'released_at' => now()->subDays(10),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-new-branch',
        'normalized_version' => 'dev-new-branch',
        'released_at' => now(),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'released_at' => now()->subDays(5),
    ]);

    $versions = $package->versions()
        ->orderBySemanticVersion('desc')
        ->pluck('version')
        ->toArray();

    expect($versions[0])->toBe('1.0.0');
    expect($versions[1])->toBe('dev-new-branch');
    expect($versions[2])->toBe('dev-old-branch');
});

it('handles 9999999-dev normalized versions', function () {
    $package = Package::factory()->create();

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-main',
        'normalized_version' => '9999999-dev',
        'released_at' => now(),
    ]);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'released_at' => now()->subDays(1),
    ]);

    $versions = $package->versions()
        ->orderBySemanticVersion('desc')
        ->pluck('version')
        ->toArray();

    expect($versions[0])->toBe('1.0.0');
    expect($versions[1])->toBe('dev-main');
});
