<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('packages', 'version-detail');

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

it('returns activeVersion as null when no version param is provided', function () {
    PackageVersion::factory()->forPackage($this->package)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('activeVersion', null)
    );
});

it('returns activeVersion when a valid version uuid is provided', function () {
    $version = PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '1.2.3',
        'normalized_version' => '1.2.3.0',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?version={$version->uuid}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('activeVersion')
        ->where('activeVersion.uuid', $version->uuid)
        ->where('activeVersion.version', '1.2.3')
    );
});

it('returns activeVersion as null when version uuid does not exist', function () {
    PackageVersion::factory()->forPackage($this->package)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?version=nonexistent-uuid");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('activeVersion', null)
    );
});

it('returns activeVersion as null when version belongs to a different package', function () {
    $otherPackage = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]);
    $otherVersion = PackageVersion::factory()->forPackage($otherPackage)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?version={$otherVersion->uuid}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('activeVersion', null)
    );
});

it('includes composer_json fields in activeVersion', function () {
    $version = PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
        'composer_json' => [
            'name' => 'vendor/test-package',
            'description' => 'A test package',
            'type' => 'library',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.1',
                'some/dependency' => '^1.0',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'Vendor\\TestPackage\\' => 'src/',
                ],
            ],
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'keywords' => ['testing', 'example'],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}?version={$version->uuid}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('activeVersion')
        ->where('activeVersion.description', 'A test package')
        ->where('activeVersion.type', 'library')
        ->where('activeVersion.license', 'MIT')
        ->has('activeVersion.require', 2)
        ->has('activeVersion.requireDev', 1)
        ->has('activeVersion.autoload', 1)
        ->has('activeVersion.authors', 1)
        ->has('activeVersion.keywords', 2)
    );
});
