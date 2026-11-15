<?php

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\SecurityAdvisoryMatch;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses()->group('packages');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => 'member',
    ]);

    $this->repository = Repository::factory()->forOrganization($this->organization)->create([
        'sync_status' => RepositorySyncStatus::Ok,
    ]);
    $this->packagist = Mirror::factory()->forOrganization($this->organization)->onDemand()->create(['name' => 'Packagist']);
    $this->satis = Mirror::factory()->forOrganization($this->organization)->syncFailed()->create(['name' => 'Satis']);

    Package::factory()->forOrganization($this->organization)->forRepository($this->repository)->create([
        'name' => 'acme/billing',
        'description' => 'Invoices and subscriptions',
    ]);
    Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/legacy-sdk']);
    Package::factory()->forOrganization($this->organization)->withoutRepository()->create([
        'name' => 'monolog/monolog',
        'mirror_uuid' => $this->packagist->uuid,
    ]);
    Package::factory()->forOrganization($this->organization)->withoutRepository()->create([
        'name' => 'symfony/console',
        'mirror_uuid' => $this->packagist->uuid,
    ]);
    Package::factory()->forOrganization($this->organization)->withoutRepository()->create([
        'name' => 'internal/tools',
        'mirror_uuid' => $this->satis->uuid,
    ]);
});

function packageNames(AssertableInertia $page): array
{
    return collect($page->toArray()['props']['packages']['data'])->pluck('name')->all();
}

function visitPackages(array $query = []): AssertableInertia
{
    $page = null;

    test()->actingAs(test()->user)
        ->get(route('organizations.packages.index', [test()->organization, ...$query]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
            $page = $inertia->component('organizations/packages');
        });

    return $page;
}

it('lists every package without filters', function () {
    expect(packageNames(visitPackages()))->toBe([
        'acme/billing',
        'acme/legacy-sdk',
        'internal/tools',
        'monolog/monolog',
        'symfony/console',
    ]);
});

it('filters by source', function (string $source, array $expected) {
    expect(packageNames(visitPackages(['source' => $source])))->toBe($expected);
})->with([
    'repository' => ['repository', ['acme/billing']],
    'upload' => ['upload', ['acme/legacy-sdk']],
    'mirror' => ['mirror', ['internal/tools', 'monolog/monolog', 'symfony/console']],
]);

it('filters by a single mirror', function () {
    $page = visitPackages(['mirror' => $this->packagist->uuid]);

    expect(packageNames($page))->toBe(['monolog/monolog', 'symfony/console']);
    $page->where('filters.source', 'mirror')->where('filters.mirror', $this->packagist->uuid);
});

it('does not show packages of another organization’s mirror', function () {
    $otherMirror = Mirror::factory()->create();
    Package::factory()->withoutRepository()->create([
        'organization_uuid' => $otherMirror->organization_uuid,
        'mirror_uuid' => $otherMirror->uuid,
    ]);

    expect(packageNames(visitPackages(['mirror' => $otherMirror->uuid])))->toBe([]);
});

it('searches name and description', function () {
    expect(packageNames(visitPackages(['query' => 'MONOLOG'])))->toBe(['monolog/monolog'])
        ->and(packageNames(visitPackages(['query' => 'subscriptions'])))->toBe(['acme/billing']);
});

it('matches underscores and percent signs literally', function () {
    Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/foo_bar', 'description' => null]);
    Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/foo-bar', 'description' => '100% coverage']);

    expect(packageNames(visitPackages(['query' => 'foo_bar'])))->toBe(['acme/foo_bar'])
        ->and(packageNames(visitPackages(['query' => '100%'])))->toBe(['acme/foo-bar'])
        ->and(packageNames(visitPackages(['query' => '%'])))->toBe(['acme/foo-bar']);
});

it('filters by a mirror that has no packages', function () {
    $emptyMirror = Mirror::factory()->forOrganization($this->organization)->create();

    $page = visitPackages(['mirror' => $emptyMirror->uuid, 'query' => 'acme']);

    expect(packageNames($page))->toBe([]);
    $page->where('filters.mirror', $emptyMirror->uuid);
});

it('filters packages with security advisories', function () {
    $package = Package::where('name', 'symfony/console')->firstOrFail();
    $version = PackageVersion::factory()->create(['package_uuid' => $package->uuid]);
    SecurityAdvisoryMatch::factory()->create(['package_version_uuid' => $version->uuid]);

    $page = visitPackages(['status' => 'vulnerable']);

    expect(packageNames($page))->toBe(['symfony/console']);
    $page->where('facets.vulnerable', 1);
});

it('filters packages whose sync failed', function () {
    expect(packageNames(visitPackages(['status' => 'sync_failed'])))->toBe(['internal/tools']);
});

it('combines filters', function () {
    expect(packageNames(visitPackages(['source' => 'mirror', 'query' => 'o', 'status' => 'sync_failed'])))
        ->toBe(['internal/tools']);
});

it('ignores unknown filter values', function () {
    $page = visitPackages(['source' => 'ftp', 'status' => 'broken', 'mirror' => 'not-a-uuid']);

    expect(packageNames($page))->toHaveCount(5);
    $page->where('filters.source', null)->where('filters.status', null)->where('filters.mirror', null);
});

it('counts sources and statuses across the whole organization', function () {
    visitPackages(['source' => 'upload'])
        ->where('facets.total', 5)
        ->where('facets.syncFailed', 1)
        ->where('facets.sources', [
            ['source' => 'repository', 'mirror' => null, 'label' => 'Repositories', 'count' => 1],
            ['source' => 'upload', 'mirror' => null, 'label' => 'Uploads', 'count' => 1],
            ['source' => 'mirror', 'mirror' => $this->packagist->uuid, 'label' => 'Packagist', 'count' => 2],
            ['source' => 'mirror', 'mirror' => $this->satis->uuid, 'label' => 'Satis', 'count' => 1],
        ]);
});

it('paginates filtered results and keeps the filters in the page links', function () {
    Package::factory()->count(55)->forOrganization($this->organization)->withoutRepository()->create([
        'mirror_uuid' => $this->packagist->uuid,
    ]);

    $page = visitPackages(['mirror' => $this->packagist->uuid, 'page' => 2]);
    $props = $page->toArray()['props']['packages'];

    expect($props['total'])->toBe(57)
        ->and($props['current_page'])->toBe(2)
        ->and($props['data'])->toHaveCount(7)
        ->and($props['prev_page_url'])->toContain('mirror='.$this->packagist->uuid);
});
