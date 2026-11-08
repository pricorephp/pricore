<?php

use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Spatie\LaravelData\DataCollection;

/**
 * @param  array<string, string>  $tags  name => commit
 * @param  array<string, string>  $branches  name => commit
 */
function branchVersionRefs(array $tags = [], array $branches = []): RefsCollectionData
{
    $tagData = array_map(fn (string $name) => new RefData($name, $tags[$name], isTag: true), array_map('strval', array_keys($tags)));
    $branchData = array_map(fn (string $name) => new RefData($name, $branches[$name]), array_map('strval', array_keys($branches)));

    return new RefsCollectionData(
        tags: new DataCollection(RefData::class, $tagData),
        branches: new DataCollection(RefData::class, $branchData),
        all: new DataCollection(RefData::class, [...$tagData, ...$branchData]),
    );
}

/**
 * Runs the steps of a repository sync and returns the names of the refs it synced.
 *
 * @return array<int, string>
 */
function syncBranchVersionRefs(Repository $repository, RefsCollectionData $refs): array
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')
        ->andReturnUsing(fn (string $ref, string $path) => $path === 'composer.json' ? (string) json_encode(['name' => 'acme/app']) : null);
    $provider->shouldReceive('listDirectory')->andReturn([]);
    $provider->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/acme/app.git');
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('acme/app');

    $repository->refresh();
    $filtered = app(FilterChangedRefsAction::class)->handle($refs, $repository);
    app(RemoveStaleVersionsAction::class)->handle($repository, $refs);

    foreach ($filtered->all as $ref) {
        app(SyncRefAction::class)->handle($provider, $repository, $ref);
    }

    return collect($filtered->all->toCollection())->pluck('name')->all();
}

/**
 * @return array<string, array{normalized: string, commit: string}>
 */
function branchVersionRows(Repository $repository): array
{
    return PackageVersion::query()
        ->whereIn('package_uuid', $repository->packages()->select('uuid'))
        ->orderBy('version')
        ->get()
        ->mapWithKeys(fn (PackageVersion $version) => [$version->version => [
            'normalized' => $version->normalized_version,
            'commit' => (string) $version->source_reference,
        ]])
        ->all();
}

beforeEach(function () {
    config(['pricore.dist.enabled' => false]);

    $this->organization = Organization::factory()->create();
    $this->repository = Repository::factory()->github()->forOrganization($this->organization)->create();
});

it('publishes numeric branches as dev versions, apart from tags of the same name', function (?string $tagFormat) {
    $this->repository->update(['tag_format' => $tagFormat]);
    $refs = branchVersionRefs(tags: ['2.0' => 'tag-commit'], branches: ['2.0' => 'branch-commit', '1.2.x' => 'wildcard-commit', 'main' => 'main-commit']);

    syncBranchVersionRefs($this->repository, $refs);

    expect(branchVersionRows($this->repository))->toBe([
        '1.2.x-dev' => ['normalized' => '1.2.9999999.9999999-dev', 'commit' => 'wildcard-commit'],
        '2.0' => ['normalized' => '2.0.0.0', 'commit' => 'tag-commit'],
        '2.0.x-dev' => ['normalized' => '2.0.9999999.9999999-dev', 'commit' => 'branch-commit'],
        'dev-main' => ['normalized' => 'dev-main', 'commit' => 'main-commit'],
    ])->and(syncBranchVersionRefs($this->repository, $refs))->toBe([]);
})->with([
    'without tag format' => [null],
    'with tag format' => ['{package}@{version}'],
]);

it('renames a branch version stored under the old naming in a single sync', function (?string $tagFormat) {
    $this->repository->update(['tag_format' => $tagFormat]);
    $package = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->create(['name' => 'acme/app']);
    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'dev-1.2.x',
        'normalized_version' => 'dev-1.2.x',
        'source_reference' => 'wildcard-commit',
        'source_tag' => '1.2.x',
    ]);
    $refs = branchVersionRefs(branches: ['1.2.x' => 'wildcard-commit']);

    expect(syncBranchVersionRefs($this->repository, $refs))->toBe(['1.2.x'])
        ->and(branchVersionRows($this->repository))->toBe([
            '1.2.x-dev' => ['normalized' => '1.2.9999999.9999999-dev', 'commit' => 'wildcard-commit'],
        ])
        ->and(syncBranchVersionRefs($this->repository, $refs))->toBe([])
        ->and(branchVersionRows($this->repository))->toHaveKeys(['1.2.x-dev']);
})->with([
    'without tag format' => [null],
    'with tag format' => ['{package}@{version}'],
]);
