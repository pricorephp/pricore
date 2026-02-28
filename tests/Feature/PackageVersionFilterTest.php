<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('packages', 'filters');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
    $this->package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]);
});

it('returns all versions when no filters are applied', function () {
    PackageVersion::factory()->forPackage($this->package)->count(3)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 3)
        ->where('filters.query', '')
        ->where('filters.type', '')
    );
});

it('filters versions by version query string', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.1.0',
        'normalized_version' => '1.1.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?query=1.0.0");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 1)
        ->where('versions.data.0.version', '1.0.0')
        ->where('filters.query', '1.0.0')
    );
});

it('filters versions by source reference via query', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_reference' => 'abc1234def5678',
    ]);
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
        'source_reference' => 'xyz9876fed4321',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?query=abc1234");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 1)
        ->where('versions.data.0.version', '1.0.0')
    );
});

it('filters versions by stable type', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->devBranch('main')->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?type=stable");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 1)
        ->where('versions.data.0.version', '1.0.0')
        ->where('filters.type', 'stable')
    );
});

it('filters versions by dev type', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->devBranch('main')->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?type=dev");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 1)
        ->where('versions.data.0.version', 'dev-main')
        ->where('filters.type', 'dev')
    );
});

it('applies combined query and type filters', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.1.0',
        'normalized_version' => '1.1.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->devBranch('main')->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?query=1.0.0&type=stable");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 1)
        ->where('versions.data.0.version', '1.0.0')
    );
});

it('returns empty results when no versions match filters', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?query=nonexistent");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 0)
    );
});

it('ignores unknown type filter value and returns all versions', function () {
    PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
    ]);
    PackageVersion::factory()->forPackage($this->package)->devBranch('main')->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?type=unknown");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('versions.data', 2)
    );
});
