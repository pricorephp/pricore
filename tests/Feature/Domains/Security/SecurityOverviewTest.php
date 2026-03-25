<?php

use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\SecurityAdvisory;
use App\Models\SecurityAdvisoryMatch;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'owner_uuid' => $this->user->uuid,
    ]);
    $this->organization->members()->attach($this->user->uuid, [
        'role' => 'owner',
        'uuid' => (string) Str::uuid(),
    ]);
});

it('shows security overview page with vulnerability stats', function () {
    $package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'name' => 'acme/package',
    ]);

    $version = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
    ]);

    $advisory = SecurityAdvisory::factory()->create([
        'package_name' => 'acme/package',
        'severity' => AdvisorySeverity::Critical,
    ]);

    SecurityAdvisoryMatch::factory()->create([
        'security_advisory_uuid' => $advisory->uuid,
        'package_version_uuid' => $version->uuid,
        'match_type' => AdvisoryMatchType::Direct,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/security");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/security/index')
        ->has('stats')
        ->where('stats.totalVulnerabilities', 1)
        ->where('stats.criticalCount', 1)
        ->has('packages', 1)
    );
});

it('shows empty state when no vulnerabilities', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/security");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('stats.totalVulnerabilities', 0)
        ->has('packages', 0)
    );
});

it('filters by severity', function () {
    $package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]);

    $version = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
    ]);

    $criticalAdvisory = SecurityAdvisory::factory()->create([
        'severity' => AdvisorySeverity::Critical,
    ]);

    $lowAdvisory = SecurityAdvisory::factory()->create([
        'severity' => AdvisorySeverity::Low,
    ]);

    SecurityAdvisoryMatch::factory()->create([
        'security_advisory_uuid' => $criticalAdvisory->uuid,
        'package_version_uuid' => $version->uuid,
    ]);

    SecurityAdvisoryMatch::factory()->create([
        'security_advisory_uuid' => $lowAdvisory->uuid,
        'package_version_uuid' => $version->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/security?severity=critical");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('packages', 1)
    );
});

it('only shows vulnerabilities for the current organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherPackage = Package::factory()->create([
        'organization_uuid' => $otherOrg->uuid,
    ]);
    $otherVersion = PackageVersion::factory()->create([
        'package_uuid' => $otherPackage->uuid,
    ]);
    $advisory = SecurityAdvisory::factory()->create();
    SecurityAdvisoryMatch::factory()->create([
        'security_advisory_uuid' => $advisory->uuid,
        'package_version_uuid' => $otherVersion->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/security");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('stats.totalVulnerabilities', 0)
        ->has('packages', 0)
    );
});
