<?php

use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

function makeRefs(array $tags = [], array $branches = []): RefsCollectionData
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

it('keeps all refs when repository has no packages', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $refs = makeRefs(
        tags: [
            ['name' => 'v1.0.0', 'commit' => 'abc123'],
            ['name' => 'v2.0.0', 'commit' => 'def456'],
        ],
        branches: [
            ['name' => 'main', 'commit' => 'ghi789'],
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->all->count())->toBe(3);
    expect($result->tags->count())->toBe(2);
    expect($result->branches->count())->toBe(1);
});

it('filters out refs with unchanged commit SHAs', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $package = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create();

    // Existing version with matching SHA
    PackageVersion::factory()
        ->forPackage($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123',
        ]);

    $refs = makeRefs(
        tags: [
            ['name' => 'v1.0.0', 'commit' => 'abc123'], // unchanged - should be filtered
            ['name' => 'v2.0.0', 'commit' => 'def456'], // new - should remain
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->all->count())->toBe(1);
    expect($result->tags->count())->toBe(1);
    expect($result->tags->toArray()[0]['name'])->toBe('v2.0.0');
});

it('keeps refs with changed commit SHAs', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $package = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create();

    PackageVersion::factory()
        ->forPackage($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'old-sha',
        ]);

    $refs = makeRefs(
        tags: [
            ['name' => 'v1.0.0', 'commit' => 'new-sha'], // changed SHA - should remain
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->all->count())->toBe(1);
    expect($result->tags->count())->toBe(1);
});

it('handles branch refs with dev- prefix correctly', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $package = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create();

    PackageVersion::factory()
        ->forPackage($package)
        ->devBranch('main')
        ->create([
            'source_reference' => 'abc123',
        ]);

    $refs = makeRefs(
        branches: [
            ['name' => 'main', 'commit' => 'abc123'],     // unchanged - should be filtered
            ['name' => 'develop', 'commit' => 'def456'],   // new - should remain
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->all->count())->toBe(1);
    expect($result->branches->count())->toBe(1);
    expect($result->branches->toArray()[0]['name'])->toBe('develop');
});

it('filters tags and branches independently', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $package = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create();

    PackageVersion::factory()
        ->forPackage($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'tag-sha',
        ]);

    PackageVersion::factory()
        ->forPackage($package)
        ->devBranch('main')
        ->create([
            'source_reference' => 'branch-sha',
        ]);

    $refs = makeRefs(
        tags: [
            ['name' => 'v1.0.0', 'commit' => 'tag-sha'],    // unchanged
            ['name' => 'v2.0.0', 'commit' => 'new-tag-sha'], // new
        ],
        branches: [
            ['name' => 'main', 'commit' => 'branch-sha'],       // unchanged
            ['name' => 'develop', 'commit' => 'new-branch-sha'], // new
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->tags->count())->toBe(1);
    expect($result->branches->count())->toBe(1);
    expect($result->all->count())->toBe(2);
});

it('handles tags without v prefix', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $package = Package::factory()
        ->forOrganization($organization)
        ->forRepository($repository)
        ->create();

    PackageVersion::factory()
        ->forPackage($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123',
        ]);

    $refs = makeRefs(
        tags: [
            ['name' => '1.0.0', 'commit' => 'abc123'], // unchanged, no v prefix
        ],
    );

    $action = app(FilterChangedRefsAction::class);
    $result = $action->handle($refs, $repository);

    expect($result->all->count())->toBe(0);
});
