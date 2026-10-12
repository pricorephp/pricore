<?php

use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\PruneEmptyRefsAction;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\RepositoryEmptyRef;
use Spatie\LaravelData\DataCollection;

/**
 * @param  array<int, array{name: string, commit: string}>  $tags
 */
function emptyRefsCollection(array $tags): RefsCollectionData
{
    $refs = array_map(fn (array $ref) => RefData::from($ref), $tags);

    return new RefsCollectionData(
        tags: new DataCollection(RefData::class, $refs),
        branches: new DataCollection(RefData::class, []),
        all: new DataCollection(RefData::class, $refs),
    );
}

/**
 * @param  array<string, string>  $files  path => contents
 */
function emptyRefsProvider(array $files = []): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')
        ->andReturnUsing(fn (string $ref, string $path) => $files[$path] ?? null);
    $provider->shouldReceive('listDirectory')->andReturn([]);
    $provider->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/acme/app.git');
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('acme/app');

    return $provider;
}

beforeEach(function () {
    config(['pricore.dist.enabled' => false]);

    $this->organization = Organization::factory()->create();
    $this->repository = Repository::factory()->github()->forOrganization($this->organization)->create();
});

it('remembers a ref without a composer.json', function () {
    $result = app(SyncRefAction::class)->handle(
        emptyRefsProvider(),
        $this->repository,
        new RefData(name: 'docs-v1.0.0', commit: 'abc123'),
    );

    expect($result->empty)->toBeTrue()
        ->and($this->repository->emptyRefs()->pluck('commit', 'name')->all())->toBe(['docs-v1.0.0' => 'abc123']);
});

it('forgets an empty ref once it holds a composer.json', function () {
    RepositoryEmptyRef::query()->create([
        'repository_uuid' => $this->repository->uuid,
        'name' => 'main',
        'commit' => 'abc123',
        'checked_at' => now(),
    ]);

    $result = app(SyncRefAction::class)->handle(
        emptyRefsProvider(['composer.json' => (string) json_encode(['name' => 'acme/app'])]),
        $this->repository,
        new RefData(name: 'main', commit: 'def456'),
    );

    expect($result->empty)->toBeFalse()
        ->and($this->repository->emptyRefs()->count())->toBe(0);
});

it('remembers a monorepo ref where no configured path holds a package', function () {
    $this->repository->update(['package_paths' => ['packages/*', 'php/client']]);

    $result = app(SyncRefAction::class)->handle(
        emptyRefsProvider(),
        $this->repository,
        new RefData(name: 'docs-v1.0.0', commit: 'abc123'),
    );

    expect($result->empty)->toBeTrue()
        ->and($this->repository->emptyRefs()->pluck('name')->all())->toBe(['docs-v1.0.0']);
});

it('does not remember a monorepo ref where one of the paths holds a package', function () {
    $this->repository->update(['package_paths' => ['packages/*', 'php/client']]);

    app(SyncRefAction::class)->handle(
        emptyRefsProvider(['php/client/composer.json' => (string) json_encode(['name' => 'acme/client'])]),
        $this->repository,
        new RefData(name: 'v1.0.0', commit: 'abc123'),
    );

    expect($this->repository->emptyRefs()->count())->toBe(0);
});

it('does not remember a ref whose composer.json is invalid', function () {
    app(SyncRefAction::class)->handle(
        emptyRefsProvider(['composer.json' => '{invalid']),
        $this->repository,
        new RefData(name: 'v1.0.0', commit: 'abc123'),
    );

    expect($this->repository->emptyRefs()->count())->toBe(0);
});

it('skips an empty ref while its commit stays the same', function () {
    RepositoryEmptyRef::query()->create([
        'repository_uuid' => $this->repository->uuid,
        'name' => 'docs-v1.0.0',
        'commit' => 'abc123',
        'checked_at' => now(),
    ]);

    $result = app(FilterChangedRefsAction::class)->handle(emptyRefsCollection([
        ['name' => 'docs-v1.0.0', 'commit' => 'abc123'],
        ['name' => 'docs-v1.1.0', 'commit' => 'def456'],
    ]), $this->repository);

    expect(collect($result->all->toArray())->pluck('name')->all())->toBe(['docs-v1.1.0']);
});

it('checks an empty ref again once it moved to another commit', function () {
    RepositoryEmptyRef::query()->create([
        'repository_uuid' => $this->repository->uuid,
        'name' => 'main',
        'commit' => 'abc123',
        'checked_at' => now(),
    ]);

    $result = app(FilterChangedRefsAction::class)->handle(emptyRefsCollection([
        ['name' => 'main', 'commit' => 'def456'],
    ]), $this->repository);

    expect($result->all->count())->toBe(1);
});

it('skips empty refs alongside unchanged package versions', function () {
    $package = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)->create();
    PackageVersion::factory()->forPackage($package)->create(['version' => 'v1.0.0', 'source_reference' => 'abc123']);

    RepositoryEmptyRef::query()->create([
        'repository_uuid' => $this->repository->uuid,
        'name' => 'cli-v2.0.0',
        'commit' => 'def456',
        'checked_at' => now(),
    ]);

    $result = app(FilterChangedRefsAction::class)->handle(emptyRefsCollection([
        ['name' => 'v1.0.0', 'commit' => 'abc123'],
        ['name' => 'cli-v2.0.0', 'commit' => 'def456'],
        ['name' => 'v1.1.0', 'commit' => 'ghi789'],
    ]), $this->repository);

    expect(collect($result->all->toArray())->pluck('name')->all())->toBe(['v1.1.0']);
});

it('forgets empty refs that no longer exist', function () {
    foreach (['main' => 'abc123', 'feature/gone' => 'def456'] as $name => $commit) {
        RepositoryEmptyRef::query()->create([
            'repository_uuid' => $this->repository->uuid,
            'name' => $name,
            'commit' => $commit,
            'checked_at' => now(),
        ]);
    }

    app(PruneEmptyRefsAction::class)->handle($this->repository, emptyRefsCollection([
        ['name' => 'main', 'commit' => 'abc123'],
    ]));

    expect($this->repository->emptyRefs()->pluck('name')->all())->toBe(['main']);
});

it('keeps empty refs when no refs were listed at all', function () {
    RepositoryEmptyRef::query()->create([
        'repository_uuid' => $this->repository->uuid,
        'name' => 'main',
        'commit' => 'abc123',
        'checked_at' => now(),
    ]);

    app(PruneEmptyRefsAction::class)->handle($this->repository, emptyRefsCollection([]));

    expect($this->repository->emptyRefs()->count())->toBe(1);
});
