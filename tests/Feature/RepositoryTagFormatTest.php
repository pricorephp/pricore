<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelData\DataCollection;

use function Pest\Laravel\actingAs;

uses()->group('tag-format');

/**
 * @param  array<int, string>  $tags
 * @param  array<int, string>  $branches
 */
function tagFormatRefs(array $tags = [], array $branches = []): RefsCollectionData
{
    $tagData = array_map(fn (string $name) => new RefData($name, "commit-{$name}", isTag: true), $tags);
    $branchData = array_map(fn (string $name) => new RefData($name, "commit-{$name}"), $branches);

    return new RefsCollectionData(
        tags: new DataCollection(RefData::class, $tagData),
        branches: new DataCollection(RefData::class, $branchData),
        all: new DataCollection(RefData::class, [...$tagData, ...$branchData]),
    );
}

function tagFormatVersion(Package $package, string $version, string $sourceTag, ?string $commit = null): PackageVersion
{
    return PackageVersion::factory()->forPackage($package)->atPath((string) $package->source_path)->create([
        'version' => $version,
        'normalized_version' => $version,
        'source_tag' => $sourceTag,
        'source_reference' => $commit ?? "commit-{$sourceTag}",
    ]);
}

beforeEach(function () {
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->admin->uuid, 'slug' => 'acme']);
    joinOrganization($this->organization, $this->admin, OrganizationRole::Admin);

    $this->repository = Repository::factory()
        ->forOrganization($this->organization)
        ->withPackagePaths(['packages/*'])
        ->withTagFormat('{package}@{version}')
        ->create();

    $this->billing = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->atPath('packages/billing')->create(['name' => 'acme/billing']);
    $this->crm = Package::factory()->forOrganization($this->organization)->forRepository($this->repository)
        ->atPath('packages/crm')->create(['name' => 'acme/crm']);
});

it('skips tags that release nothing and compares each tag with its own versions', function () {
    tagFormatVersion($this->billing, '1.0.0', 'billing@1.0.0');
    tagFormatVersion($this->crm, '1.0.0', 'crm@1.0.0', commit: 'an-older-commit');

    $filtered = app(FilterChangedRefsAction::class)->handle(
        tagFormatRefs(['billing@1.0.0', 'crm@1.0.0', 'nightly']),
        $this->repository,
    );

    expect(collect($filtered->all->toCollection())->pluck('name')->all())->toBe(['crm@1.0.0']);
});

it('keeps the shared versions and those of each package\'s own tags', function () {
    tagFormatVersion($this->billing, '1.0.0', 'billing@1.0.0');
    tagFormatVersion($this->billing, '2.0.0', 'billing@2.0.0');
    tagFormatVersion($this->crm, '1.0.0', 'crm@1.0.0');
    tagFormatVersion($this->crm, 'v3.0.0', 'v3.0.0');
    tagFormatVersion($this->crm, 'dev-main', 'main');
    tagFormatVersion($this->crm, 'dev-billing@2.0.0', 'billing@2.0.0');

    $removed = app(RemoveStaleVersionsAction::class)->handle(
        $this->repository,
        tagFormatRefs(['billing@1.0.0', 'crm@1.0.0', 'v3.0.0'], ['main']),
    );

    expect($removed)->toBe(2)
        ->and($this->billing->versions()->pluck('version')->all())->toBe(['1.0.0'])
        ->and($this->crm->versions()->orderBy('version')->pluck('version')->all())->toBe(['1.0.0', 'dev-main', 'v3.0.0']);
});

it('lets an admin set the tag format and starts a forced sync', function () {
    $repository = Repository::factory()->forOrganization($this->organization)->create();

    actingAs($this->admin)
        ->patch(route('organizations.repositories.update', [$this->organization->slug, $repository->uuid]), [
            'tag_format' => '{path}/{version}',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Tag format updated and a sync has been started.');

    expect($repository->fresh()->tag_format)->toBe('{path}/{version}')
        ->and($repository->fresh()->full_sync_requested_at)->not->toBeNull();
    Queue::assertPushed(SyncRepositoryJob::class);
});

it('clears the tag format when it is sent empty', function () {
    actingAs($this->admin)
        ->patch(route('organizations.repositories.update', [$this->organization->slug, $this->repository->uuid]), [
            'tag_format' => '',
        ])
        ->assertRedirect();

    expect($this->repository->fresh()->tag_format)->toBeNull();
});

it('leaves the tag format alone when only the package paths change', function () {
    actingAs($this->admin)
        ->patch(route('organizations.repositories.update', [$this->organization->slug, $this->repository->uuid]), [
            'package_paths' => "packages/*\nlibs/*",
        ])
        ->assertRedirect();

    expect($this->repository->fresh()->tag_format)->toBe('{package}@{version}');
});

it('rejects an invalid tag format', function () {
    actingAs($this->admin)
        ->patch(route('organizations.repositories.update', [$this->organization->slug, $this->repository->uuid]), [
            'tag_format' => 'release-{package}',
        ])
        ->assertSessionHasErrors('tag_format');

    expect($this->repository->fresh()->tag_format)->toBe('{package}@{version}');
    Queue::assertNothingPushed();
});

it('sets the tag format through the API', function () {
    $token = personalAccessToken($this->admin, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/acme/repositories/{$this->repository->uuid}", [
            'tag_format' => '{name}@{version}',
        ])
        ->assertOk()
        ->assertJsonPath('tagFormat', '{name}@{version}');

    Queue::assertPushed(SyncRepositoryJob::class);
});

it('previews which package and version recent tags release', function () {
    Cache::put("repository:{$this->repository->uuid}:tag-names", ['billing@1.2.0', 'newpkg@0.1.0', 'v2.0.0', 'nightly']);

    actingAs($this->admin)
        ->getJson(route('organizations.repositories.tag-format-preview', [$this->organization->slug, $this->repository->uuid, 'tag_format' => '{package}@{version}']))
        ->assertOk()
        ->assertJson(['tags' => [
            ['tag' => 'v2.0.0', 'release' => 'shared', 'version' => 'v2.0.0', 'package' => null],
            ['tag' => 'nightly', 'release' => 'ignored', 'version' => null],
            ['tag' => 'newpkg@0.1.0', 'release' => 'package', 'version' => '0.1.0', 'package' => 'newpkg', 'packageKnown' => false],
            ['tag' => 'billing@1.2.0', 'release' => 'package', 'version' => '1.2.0', 'package' => 'acme/billing', 'packageKnown' => true],
        ]]);
});

it('rejects previews of invalid formats and from members', function () {
    actingAs($this->admin)
        ->getJson(route('organizations.repositories.tag-format-preview', [$this->organization->slug, $this->repository->uuid, 'tag_format' => '{package}']))
        ->assertUnprocessable();

    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);

    actingAs($member)
        ->getJson(route('organizations.repositories.tag-format-preview', [$this->organization->slug, $this->repository->uuid, 'tag_format' => '{package}@{version}']))
        ->assertForbidden();
});
