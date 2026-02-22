<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageDownload;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('packages', 'downloads');

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

describe('package download stats', function () {
    it('returns download stats on package show page', function () {
        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('organizations/packages/show')
            ->has('downloadStats')
            ->has('downloadStats.totalDownloads')
            ->has('downloadStats.dailyDownloads')
            ->has('downloadStats.versionBreakdown')
        );
    });

    it('counts total downloads correctly', function () {
        PackageDownload::factory()
            ->count(7)
            ->forOrganization($this->organization)
            ->forPackage($this->package)
            ->create();

        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertInertia(fn ($page) => $page
            ->where('downloadStats.totalDownloads', 7)
        );
    });

    it('groups downloads by version', function () {
        PackageDownload::factory()
            ->count(5)
            ->forOrganization($this->organization)
            ->forPackage($this->package)
            ->create(['version' => '1.0.0']);

        PackageDownload::factory()
            ->count(3)
            ->forOrganization($this->organization)
            ->forPackage($this->package)
            ->create(['version' => '2.0.0']);

        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertInertia(fn ($page) => $page
            ->has('downloadStats.versionBreakdown', 2)
            ->where('downloadStats.versionBreakdown.0.version', '1.0.0')
            ->where('downloadStats.versionBreakdown.0.downloads', 5)
            ->where('downloadStats.versionBreakdown.1.version', '2.0.0')
            ->where('downloadStats.versionBreakdown.1.downloads', 3)
        );
    });

    it('returns 30 days of daily download data', function () {
        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertInertia(fn ($page) => $page
            ->has('downloadStats.dailyDownloads', 30)
        );
    });

    it('returns empty stats for package with no downloads', function () {
        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertInertia(fn ($page) => $page
            ->where('downloadStats.totalDownloads', 0)
            ->has('downloadStats.dailyDownloads', 30)
            ->has('downloadStats.versionBreakdown', 0)
        );
    });

    it('does not count downloads from other packages', function () {
        $otherPackage = Package::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);

        PackageDownload::factory()
            ->count(5)
            ->forOrganization($this->organization)
            ->forPackage($otherPackage)
            ->create();

        PackageDownload::factory()
            ->count(2)
            ->forOrganization($this->organization)
            ->forPackage($this->package)
            ->create();

        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}/packages/{$this->package->uuid}");

        $response->assertInertia(fn ($page) => $page
            ->where('downloadStats.totalDownloads', 2)
        );
    });
});

describe('organization download stats', function () {
    it('returns total downloads in organization stats', function () {
        PackageDownload::factory()
            ->count(10)
            ->forOrganization($this->organization)
            ->forPackage($this->package)
            ->create();

        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalDownloads', 10)
            ->has('stats.dailyDownloads', 30)
        );
    });

    it('returns zero downloads for organization with no downloads', function () {
        $response = $this->actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalDownloads', 0)
            ->has('stats.dailyDownloads', 30)
        );
    });
});
