<?php

use App\Domains\Repository\Actions\ResolvePackagePathsAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Repository;

/**
 * @param  array<string, array<int, array{name: string, type: string}>>  $directories  path => entries
 */
function directoryListingProvider(array $directories = []): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('listDirectory')
        ->andReturnUsing(fn (string $ref, string $path) => $directories[$path] ?? []);

    return $provider;
}

it('resolves to the root when no package paths are configured', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldNotReceive('listDirectory');

    $action = app(ResolvePackagePathsAction::class);

    expect($action->handle($provider, Repository::factory()->create(), 'main'))->toBe([''])
        ->and($action->handle($provider, Repository::factory()->withPackagePaths([])->create(), 'main'))->toBe(['']);
});

it('returns literal directories and the root when listed', function () {
    $repository = Repository::factory()->withPackagePaths(['packages/billing', '.', 'packages/crm/'])->create();

    expect(app(ResolvePackagePathsAction::class)->handle(directoryListingProvider(), $repository, 'main'))
        ->toBe(['', 'packages/billing', 'packages/crm']);
});

it('leaves the root out unless it is listed', function () {
    $repository = Repository::factory()->withPackagePaths(['packages/billing'])->create();

    expect(app(ResolvePackagePathsAction::class)->handle(directoryListingProvider(), $repository, 'main'))
        ->toBe(['packages/billing']);
});

it('expands a wildcard to the subdirectories of its parent', function () {
    $repository = Repository::factory()->withPackagePaths(['packages/*'])->create();

    $provider = directoryListingProvider([
        'packages' => [
            ['name' => 'crm', 'type' => 'dir'],
            ['name' => 'billing', 'type' => 'dir'],
            ['name' => 'README.md', 'type' => 'file'],
            ['name' => '.hidden', 'type' => 'dir'],
            ['name' => 'bad name', 'type' => 'dir'],
        ],
    ]);

    expect(app(ResolvePackagePathsAction::class)->handle($provider, $repository, 'v1.0.0'))
        ->toBe(['packages/billing', 'packages/crm']);
});

it('expands a bare wildcard against the repository root', function () {
    $repository = Repository::factory()->withPackagePaths(['*'])->create();

    $provider = directoryListingProvider([
        '' => [
            ['name' => 'billing', 'type' => 'dir'],
            ['name' => 'composer.json', 'type' => 'file'],
        ],
    ]);

    expect(app(ResolvePackagePathsAction::class)->handle($provider, $repository, 'main'))->toBe(['billing']);
});

it('deduplicates overlapping patterns and ignores invalid ones', function () {
    $repository = Repository::factory()->withPackagePaths(['packages/*', 'packages/billing', '../escape', 'packages/*/src'])->create();

    $provider = directoryListingProvider([
        'packages' => [['name' => 'billing', 'type' => 'dir']],
    ]);

    expect(app(ResolvePackagePathsAction::class)->handle($provider, $repository, 'main'))->toBe(['packages/billing']);
});
