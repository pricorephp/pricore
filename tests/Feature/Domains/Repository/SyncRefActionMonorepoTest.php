<?php

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, string>  $files  path => contents
 * @param  array<string, array<int, array{name: string, type: string}>>  $directories  path => entries
 */
function monorepoProvider(array $files, array $directories = []): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')
        ->andReturnUsing(fn (string $ref, string $path) => $files[$path] ?? null);
    $provider->shouldReceive('listDirectory')
        ->andReturnUsing(fn (string $ref, string $path) => $directories[trim($path, '/')] ?? []);
    $provider->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/acme/monorepo.git');
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('acme/monorepo');
    $provider->shouldReceive('downloadArchive')
        ->andReturnUsing(function (string $ref, string $outputPath, ?string $path = null) {
            file_put_contents($outputPath, "zip-{$ref}-".($path ?? 'root'));

            return true;
        });

    return $provider;
}

function monorepoComposerJson(string $name): string
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
        ->create();

    $this->packagesListing = [
        'packages' => [
            ['name' => 'billing', 'type' => 'dir'],
            ['name' => 'crm', 'type' => 'dir'],
            ['name' => 'README.md', 'type' => 'file'],
        ],
    ];

    $this->sync = fn (GitProviderInterface $provider, string $ref = 'main', string $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa') => app(SyncRefAction::class)
        ->handle($provider, $this->repository, new RefData(name: $ref, commit: $commit));
});

it('syncs every package found under the configured paths and leaves the root alone', function () {
    $provider = monorepoProvider([
        'composer.json' => monorepoComposerJson('acme/monorepo'),
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => monorepoComposerJson('acme/crm'),
    ], $this->packagesListing);

    $commit = 'abc123def4567890abc123def4567890abc123de';
    $result = ($this->sync)($provider, 'v1.0.0', $commit);

    expect($result->added)->toBe(2)
        ->and($result->packagesFound)->toBe(2)
        ->and($result->skipped)->toBe(0)
        ->and($result->removed)->toBe(0)
        ->and(Package::query()->orderBy('name')->pluck('name')->all())->toBe(['acme/billing', 'acme/crm']);

    $billing = Package::query()->where('name', 'acme/billing')->sole();
    $version = $billing->versions()->sole();

    expect($billing->source_path)->toBe('packages/billing')
        ->and($billing->repository_uuid)->toBe($this->repository->uuid)
        ->and($version->version)->toBe('v1.0.0')
        ->and($version->source_path)->toBe('packages/billing')
        ->and($version->source_reference)->toBe($commit)
        ->and($version->dist_shasum)->toBe(sha1("zip-{$commit}-packages/billing"));

    expect(VersionMetadataData::fromPackageVersion($version)->toArray())
        ->not->toHaveKey('source')
        ->toHaveKey('dist');
});

it('includes the root package only when the root is listed', function () {
    $this->repository->update(['package_paths' => ['.', 'packages/*']]);

    $provider = monorepoProvider([
        'composer.json' => monorepoComposerJson('acme/monorepo'),
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => monorepoComposerJson('acme/crm'),
    ], $this->packagesListing);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(3)
        ->and(Package::query()->where('name', 'acme/monorepo')->sole()->source_path)->toBeNull()
        ->and(PackageVersion::query()->whereNull('source_path')->count())->toBe(1);
});

it('reads the README from the package directory', function () {
    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/billing/readme.md' => '# Billing',
        'README.md' => '# Monorepo',
    ], $this->packagesListing + [
        'packages/billing' => [
            ['name' => 'composer.json', 'type' => 'file'],
            ['name' => 'readme.md', 'type' => 'file'],
        ],
    ]);

    ($this->sync)($provider);

    expect(PackageVersion::query()->sole()->readme)->toBe('# Billing');
});

it('counts directories without a composer.json as skipped', function () {
    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
    ], $this->packagesListing);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and(Package::count())->toBe(1);
});

it('keeps syncing the other packages when one composer.json is invalid and leaves that package alone', function () {
    $crm = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->atPath('packages/crm')->create(['name' => 'acme/crm']);
    $existing = PackageVersion::factory()->forPackage($crm)->devBranch('main')->atPath('packages/crm')
        ->create(['source_reference' => 'oldoldoldoldoldoldoldoldoldoldoldoldoldo']);

    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => '{"name": "acme/crm", ',
    ], $this->packagesListing);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($result->removed)->toBe(0)
        ->and($existing->fresh()?->source_reference)->toBe('oldoldoldoldoldoldoldoldoldoldoldoldoldo');
});

it('skips a second directory declaring an already seen package name', function () {
    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => monorepoComposerJson('acme/billing'),
    ], $this->packagesListing);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and(Package::count())->toBe(1)
        ->and(PackageVersion::query()->sole()->source_path)->toBe('packages/billing');
});

it('does not attach versions to a package owned by another repository', function () {
    $otherRepository = Repository::factory()->github()->forOrganization($this->organization)->create();
    Package::factory()->forOrganization($this->organization)->forRepository($otherRepository)->create(['name' => 'acme/billing']);

    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => monorepoComposerJson('acme/crm'),
    ], $this->packagesListing);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and(Package::query()->where('name', 'acme/billing')->sole()->repository_uuid)->toBe($otherRepository->uuid)
        ->and(Package::query()->where('name', 'acme/billing')->sole()->versions()->count())->toBe(0);
});

it('removes the ref version of a package that vanished from the ref', function () {
    $crm = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->atPath('packages/crm')->create(['name' => 'acme/crm']);
    PackageVersion::factory()->forPackage($crm)->devBranch('main')->atPath('packages/crm')->create();
    PackageVersion::factory()->forPackage($crm)->atPath('packages/crm')->create(['version' => 'v1.0.0']);

    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
    ], ['packages' => [['name' => 'billing', 'type' => 'dir']]]);

    $result = ($this->sync)($provider);

    expect($result->added)->toBe(1)
        ->and($result->removed)->toBe(1)
        ->and($crm->versions()->pluck('version')->all())->toBe(['v1.0.0']);
});

it('records an activity for each newly discovered package', function () {
    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
        'packages/crm/composer.json' => monorepoComposerJson('acme/crm'),
    ], $this->packagesListing);

    ($this->sync)($provider);
    ($this->sync)($provider, 'v1.0.0', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');

    expect(ActivityLog::query()->where('type', ActivityType::PackageCreated->value)->count())->toBe(2);
});

it('moves a package that changed directory at the same commit', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $billing = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->atPath('src/billing')->create(['name' => 'acme/billing']);
    $version = PackageVersion::factory()->forPackage($billing)->devBranch('main')->atPath('src/billing')
        ->create(['source_reference' => $commit]);

    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
    ], ['packages' => [['name' => 'billing', 'type' => 'dir']]]);

    $result = ($this->sync)($provider, 'main', $commit);

    expect($result->updated)->toBe(1)
        ->and($version->fresh()?->source_path)->toBe('packages/billing')
        ->and($billing->fresh()?->source_path)->toBe('packages/billing');
});

it('skips a package whose ref already sits at the same commit and location', function () {
    $provider = monorepoProvider([
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
    ], ['packages' => [['name' => 'billing', 'type' => 'dir']]]);

    ($this->sync)($provider);
    $result = ($this->sync)($provider);

    expect($result->added)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and($result->packagesFound)->toBe(1)
        ->and($result->removed)->toBe(0);
});

it('keeps the classic root package flow for repositories without configured paths', function () {
    $this->repository->update(['package_paths' => null]);

    $provider = monorepoProvider([
        'composer.json' => monorepoComposerJson('acme/monorepo'),
        'packages/billing/composer.json' => monorepoComposerJson('acme/billing'),
    ]);

    $result = ($this->sync)($provider);
    $version = PackageVersion::query()->sole();

    expect($result->added)->toBe(1)
        ->and(Package::query()->sole()->name)->toBe('acme/monorepo')
        ->and($version->source_path)->toBeNull()
        ->and(VersionMetadataData::fromPackageVersion($version)->toArray())->toHaveKey('source');
});
