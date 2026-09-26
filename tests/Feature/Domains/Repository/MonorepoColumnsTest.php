<?php

use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Domains\Package\Contracts\Data\PackageVersionDetailData;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;

it('stores package paths on a repository as a list', function () {
    $repository = Repository::factory()->withPackagePaths(['packages/*', '.'])->create();

    expect($repository->fresh()?->package_paths)->toBe(['packages/*', '.'])
        ->and(RepositoryData::fromModel($repository)->packagePaths)->toBe(['packages/*', '.']);
});

it('leaves package paths and source paths empty by default', function () {
    $repository = Repository::factory()->create();
    $package = Package::factory()->forRepository($repository)->create();
    $version = PackageVersion::factory()->forPackage($package)->create();

    expect(RepositoryData::fromModel($repository)->packagePaths)->toBeNull()
        ->and(PackageData::fromModel($package)->sourcePath)->toBeNull()
        ->and(PackageVersionData::fromModel($version)->sourcePath)->toBeNull();
});

it('exposes the source path of a package and its versions', function () {
    $package = Package::factory()->atPath('packages/billing')->create();
    $version = PackageVersion::factory()->forPackage($package)->atPath('packages/billing')->create();

    expect(PackageData::fromModel($package)->sourcePath)->toBe('packages/billing')
        ->and(PackageVersionData::fromModel($version)->sourcePath)->toBe('packages/billing')
        ->and(PackageVersionDetailData::fromModel($version)->sourcePath)->toBe('packages/billing');
});
