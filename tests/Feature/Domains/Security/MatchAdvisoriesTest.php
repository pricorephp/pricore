<?php

use App\Domains\Security\Actions\MatchAdvisoriesForPackageVersionAction;
use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\SecurityAdvisory;
use App\Models\SecurityAdvisoryMatch;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'name' => 'acme/vulnerable-package',
    ]);
});

it('creates direct match when version satisfies advisory constraint', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.2.0',
        'normalized_version' => '1.2.0.0',
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::High,
    ]);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);
    $matchesCreated = $action->handle($version);

    expect($matchesCreated)->toBe(1);
    expect(SecurityAdvisoryMatch::count())->toBe(1);

    $match = SecurityAdvisoryMatch::first();
    expect($match->match_type)->toBe(AdvisoryMatchType::Direct);
    expect($match->dependency_name)->toBeNull();
});

it('does not match when version is outside advisory constraint', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::High,
    ]);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);
    $matchesCreated = $action->handle($version);

    expect($matchesCreated)->toBe(0);
    expect(SecurityAdvisoryMatch::count())->toBe(0);
});

it('creates dependency match when composer.json require references a vulnerable package', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'composer_json' => [
            'name' => 'acme/vulnerable-package',
            'require' => [
                'php' => '^8.1',
                'monolog/monolog' => '^1.0',
            ],
        ],
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'monolog/monolog',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::Critical,
    ]);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);
    $matchesCreated = $action->handle($version);

    expect($matchesCreated)->toBe(1);

    $match = SecurityAdvisoryMatch::first();
    expect($match->match_type)->toBe(AdvisoryMatchType::Dependency);
    expect($match->dependency_name)->toBe('monolog/monolog');
});

it('removes stale matches when advisory no longer applies', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
    ]);

    $advisory = SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::Medium,
    ]);

    // Manually create a match that shouldn't exist
    SecurityAdvisoryMatch::factory()->create([
        'security_advisory_uuid' => $advisory->uuid,
        'package_version_uuid' => $version->uuid,
        'match_type' => AdvisoryMatchType::Direct,
    ]);

    expect(SecurityAdvisoryMatch::count())->toBe(1);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);
    $action->handle($version);

    expect(SecurityAdvisoryMatch::count())->toBe(0);
});

it('handles multiple advisories for the same package', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.2.0',
        'normalized_version' => '1.2.0.0',
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.0,<1.3',
        'severity' => AdvisorySeverity::High,
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.1,<1.5',
        'severity' => AdvisorySeverity::Critical,
    ]);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);
    $matchesCreated = $action->handle($version);

    expect($matchesCreated)->toBe(2);
    expect(SecurityAdvisoryMatch::count())->toBe(2);
});

it('does not create duplicate matches on re-scan', function () {
    $version = PackageVersion::factory()->create([
        'package_uuid' => $this->package->uuid,
        'version' => '1.2.0',
        'normalized_version' => '1.2.0.0',
    ]);

    SecurityAdvisory::factory()->create([
        'package_name' => 'acme/vulnerable-package',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::High,
    ]);

    $action = app(MatchAdvisoriesForPackageVersionAction::class);

    $action->handle($version);
    expect(SecurityAdvisoryMatch::count())->toBe(1);

    // Re-scan should not create duplicates
    $matchesCreated = $action->handle($version->fresh());
    expect($matchesCreated)->toBe(0);
    expect(SecurityAdvisoryMatch::count())->toBe(1);
});
