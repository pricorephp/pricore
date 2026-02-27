<?php

use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

function makeRefsCollection(array $tags = [], array $branches = []): RefsCollectionData
{
    $tagData = array_map(fn (array $ref) => RefData::from($ref), $tags);
    $branchData = array_map(fn (array $ref) => RefData::from($ref), $branches);
    $allData = array_merge($tagData, $branchData);

    return new RefsCollectionData(
        tags: new DataCollection(RefData::class, $tagData),
        branches: new DataCollection(RefData::class, $branchData),
        all: new DataCollection(RefData::class, $allData),
    );
}

it('removes dev versions for deleted branches', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();

    // Create versions: dev-main (still exists), dev-feature-x (deleted)
    PackageVersion::factory()->forPackage($package)->devBranch('main')->create();
    PackageVersion::factory()->forPackage($package)->devBranch('feature-x')->create();

    $refs = makeRefsCollection(
        branches: [
            ['name' => 'main', 'commit' => 'abc123'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository, $refs);

    expect($removed)->toBe(1);
    expect(PackageVersion::where('package_uuid', $package->uuid)->count())->toBe(1);
    expect(PackageVersion::where('package_uuid', $package->uuid)->first()->version)->toBe('dev-main');
});

it('removes stable versions for deleted tags', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();

    PackageVersion::factory()->forPackage($package)->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0']);
    PackageVersion::factory()->forPackage($package)->create(['version' => '2.0.0', 'normalized_version' => '2.0.0.0']);

    $refs = makeRefsCollection(
        tags: [
            ['name' => '2.0.0', 'commit' => 'abc123'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository, $refs);

    expect($removed)->toBe(1);
    expect(PackageVersion::where('package_uuid', $package->uuid)->first()->version)->toBe('2.0.0');
});

it('preserves versions matching current refs', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();

    PackageVersion::factory()->forPackage($package)->devBranch('main')->create();
    PackageVersion::factory()->forPackage($package)->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0']);

    $refs = makeRefsCollection(
        tags: [
            ['name' => '1.0.0', 'commit' => 'abc123'],
        ],
        branches: [
            ['name' => 'main', 'commit' => 'def456'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository, $refs);

    expect($removed)->toBe(0);
    expect(PackageVersion::where('package_uuid', $package->uuid)->count())->toBe(2);
});

it('handles tags with v prefix correctly', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();
    $package = Package::factory()->forOrganization($organization)->forRepository($repository)->create();

    // Version stored without v prefix (extractVersion strips it)
    PackageVersion::factory()->forPackage($package)->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0']);

    $refs = makeRefsCollection(
        tags: [
            ['name' => 'v1.0.0', 'commit' => 'abc123'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository, $refs);

    expect($removed)->toBe(0);
});

it('returns zero when repository has no packages', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    $refs = makeRefsCollection(
        branches: [
            ['name' => 'main', 'commit' => 'abc123'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository, $refs);

    expect($removed)->toBe(0);
});

it('does not remove versions from other repositories', function () {
    $organization = Organization::factory()->create();
    $repository1 = Repository::factory()->forOrganization($organization)->create();
    $repository2 = Repository::factory()->forOrganization($organization)->create();

    $package1 = Package::factory()->forOrganization($organization)->forRepository($repository1)->create();
    $package2 = Package::factory()->forOrganization($organization)->forRepository($repository2)->create();

    PackageVersion::factory()->forPackage($package1)->devBranch('main')->create();
    PackageVersion::factory()->forPackage($package2)->devBranch('feature-x')->create();

    $refs = makeRefsCollection(
        branches: [
            ['name' => 'main', 'commit' => 'abc123'],
        ]
    );

    $action = app(RemoveStaleVersionsAction::class);
    $removed = $action->handle($repository1, $refs);

    expect($removed)->toBe(0);
    // package2's version should be untouched
    expect(PackageVersion::where('package_uuid', $package2->uuid)->count())->toBe(1);
});
