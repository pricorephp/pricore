<?php

use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Storage;

uses()->group('tag-format');

/**
 * @param  array<string, string>  $files  path => contents
 * @param  array<string, array<int, array{name: string, type: string}>>  $directories  path => entries
 * @param  array<int, string>  $requestedFiles  filled with every file read
 */
function tagFormatProvider(array $files, array $directories = [], array &$requestedFiles = []): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')
        ->andReturnUsing(function (string $ref, string $path) use ($files, &$requestedFiles) {
            $requestedFiles[] = $path;

            return $files[$path] ?? null;
        });
    $provider->shouldReceive('listDirectory')
        ->andReturnUsing(fn (string $ref, string $path) => $directories[trim($path, '/')] ?? []);
    $provider->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/acme/monorepo.git');
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('acme/monorepo');
    $provider->shouldReceive('downloadArchive')
        ->andReturnUsing(function (string $ref, string $outputPath) {
            file_put_contents($outputPath, "zip-{$ref}");

            return true;
        });

    return $provider;
}

function tagFormatComposerJson(string $name): string
{
    return (string) json_encode(['name' => $name, 'type' => 'library']);
}

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->repository = Repository::factory()
        ->github()
        ->forOrganization($this->organization)
        ->withPackagePaths(['packages/*'])
        ->withTagFormat('{package}@{version}')
        ->create();

    $this->files = [
        'packages/billing/composer.json' => tagFormatComposerJson('acme/billing'),
        'packages/crm/composer.json' => tagFormatComposerJson('acme/crm'),
    ];
    $this->directories = [
        'packages' => [
            ['name' => 'billing', 'type' => 'dir'],
            ['name' => 'crm', 'type' => 'dir'],
        ],
    ];

    $this->sync = fn (GitProviderInterface $provider, string $tag, string $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', bool $isTag = true, array $claimed = []) => app(SyncRefAction::class)
        ->handle($provider, $this->repository, new RefData(name: $tag, commit: $commit, isTag: $isTag, claimedPackageKeys: $claimed));

    $this->versionsOf = fn (string $package) => PackageVersion::query()
        ->whereHas('package', fn ($query) => $query->where('name', $package))
        ->orderBy('version')
        ->pluck('source_reference', 'version')
        ->all();
});

it('releases only the package a tag names, at the version after the prefix', function () {
    $result = ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.2.0');

    expect($result->added)->toBe(1)
        ->and($result->packagesFound)->toBe(1)
        ->and(Package::query()->pluck('name')->all())->toBe(['acme/billing'])
        ->and(($this->versionsOf)('acme/billing'))->toBe(['1.2.0' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);

    expect(PackageVersion::query()->sole()->source_tag)->toBe('billing@1.2.0');
});

it('reads only the known package directory for a package synced before', function () {
    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'main', isTag: false);

    $requested = [];
    ($this->sync)(tagFormatProvider($this->files, $this->directories, $requested), 'crm@2.0.0');

    expect(array_values(array_unique(array_filter($requested, fn (string $path) => str_ends_with($path, 'composer.json')))))
        ->toBe(['packages/crm/composer.json'])
        ->and(($this->versionsOf)('acme/crm'))->toHaveKey('2.0.0')
        ->and(($this->versionsOf)('acme/billing'))->not->toHaveKey('2.0.0');
});

it('takes the directory straight from the tag under {path}', function () {
    $this->repository->update(['tag_format' => '{path}/{version}']);

    $provider = tagFormatProvider($this->files);
    $provider->shouldNotReceive('listDirectory');

    $result = ($this->sync)($provider, 'packages/crm/v2.0.0');

    expect($result->added)->toBe(1)
        ->and(($this->versionsOf)('acme/crm'))->toBe(['v2.0.0' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
});

it('ignores a {path} tag for a directory outside the package paths', function () {
    $this->repository->update(['tag_format' => '{path}/{version}']);

    $result = ($this->sync)(tagFormatProvider($this->files + ['tools/composer.json' => tagFormatComposerJson('acme/tools')]), 'tools/v1.0.0');

    expect($result->added)->toBe(0)
        ->and(Package::query()->count())->toBe(0);
});

it('still releases every package for a plain version tag', function () {
    $result = ($this->sync)(tagFormatProvider($this->files, $this->directories), 'v1.0.0');

    expect($result->added)->toBe(2)
        ->and(($this->versionsOf)('acme/billing'))->toHaveKey('v1.0.0')
        ->and(($this->versionsOf)('acme/crm'))->toHaveKey('v1.0.0');
});

it('lets a package tag outrank a shared tag of the same version', function () {
    $packageCommit = str_repeat('a', 40);
    $sharedCommit = str_repeat('b', 40);

    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.0.0', $packageCommit);
    ($this->sync)(tagFormatProvider($this->files, $this->directories), '1.0.0', $sharedCommit, claimed: ['billing']);

    expect(($this->versionsOf)('acme/billing'))->toBe(['1.0.0' => $packageCommit])
        ->and(($this->versionsOf)('acme/crm'))->toBe(['1.0.0' => $sharedCommit]);

    // And the other way around: the package tag takes the version over
    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'crm@1.0.0', $packageCommit);

    expect(($this->versionsOf)('acme/crm'))->toBe(['1.0.0' => $packageCommit]);
});

it('leaves versions of other packages alone when their tag shares the version', function () {
    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'crm@1.0.0');
    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.0.0');

    expect(($this->versionsOf)('acme/crm'))->toHaveKey('1.0.0')
        ->and(($this->versionsOf)('acme/billing'))->toHaveKey('1.0.0');
});

it('releases nothing for tags outside the format', function () {
    $provider = tagFormatProvider($this->files, $this->directories);
    $provider->shouldNotReceive('getFileContent');

    $result = ($this->sync)($provider, 'nightly');

    expect($result->added)->toBe(0)
        ->and($result->packagesFound)->toBe(0)
        ->and(PackageVersion::query()->count())->toBe(0);
});

it('releases a shared tag again for a package whose own tag is gone', function () {
    $sharedCommit = str_repeat('b', 40);

    ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.0.0', str_repeat('a', 40));
    ($this->sync)(tagFormatProvider($this->files, $this->directories), '1.0.0', $sharedCommit);

    expect(($this->versionsOf)('acme/billing'))->toBe(['1.0.0' => $sharedCommit])
        ->and(PackageVersion::query()->whereHas('package', fn ($query) => $query->where('name', 'acme/billing'))->sole()->source_tag)->toBe('1.0.0');
});

it('takes a version over from a shared tag at the same commit', function () {
    ($this->sync)(tagFormatProvider($this->files, $this->directories), '1.0.0');
    $result = ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.0.0');

    expect($result->added + $result->updated)->toBe(0)
        ->and(PackageVersion::query()->whereHas('package', fn ($query) => $query->where('name', 'acme/billing'))->sole()->source_tag)->toBe('billing@1.0.0')
        ->and(PackageVersion::query()->whereHas('package', fn ($query) => $query->where('name', 'acme/crm'))->sole()->source_tag)->toBe('1.0.0');
});

it('finds a package that lived in another directory at an older tag', function () {
    ($this->sync)(tagFormatProvider([
        'packages/payments/composer.json' => tagFormatComposerJson('acme/billing'),
        'packages/crm/composer.json' => tagFormatComposerJson('acme/crm'),
    ], ['packages' => [['name' => 'payments', 'type' => 'dir'], ['name' => 'crm', 'type' => 'dir']]]), 'main', isTag: false);

    expect(Package::query()->where('name', 'acme/billing')->sole()->source_path)->toBe('packages/payments');

    $result = ($this->sync)(tagFormatProvider($this->files, $this->directories), 'billing@1.0.0');

    $version = PackageVersion::query()->where('version', '1.0.0')->sole();

    expect($result->added)->toBe(1)
        ->and($result->empty)->toBeFalse()
        ->and($version->package->name)->toBe('acme/billing')
        ->and($version->source_path)->toBe('packages/billing');
});
