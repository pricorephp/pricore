<?php

use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\SecurityAdvisory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'acme',
        'owner_uuid' => $this->user->uuid,
    ]);

    $this->plainToken = 'test-token-'.uniqid();
    $this->accessToken = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();
});

it('returns advisories for requested packages', function () {
    SecurityAdvisory::factory()->create([
        'advisory_id' => 'PKSA-001',
        'package_name' => 'monolog/monolog',
        'title' => 'RCE vulnerability',
        'cve' => 'CVE-2024-0001',
        'affected_versions' => '>=1.0,<1.5',
        'severity' => AdvisorySeverity::High,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->plainToken}",
    ])->postJson(
        "/{$this->organization->slug}/api/security-advisories",
        ['packages' => ['monolog/monolog']],
    );

    $response->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('advisories');
    expect($data['advisories'])->toHaveKey('monolog/monolog');
    expect($data['advisories']['monolog/monolog'])->toHaveCount(1);

    $advisory = $data['advisories']['monolog/monolog'][0];
    expect($advisory['advisoryId'])->toBe('PKSA-001');
    expect($advisory['packageName'])->toBe('monolog/monolog');
    expect($advisory['severity'])->toBe('high');
    expect($advisory['cve'])->toBe('CVE-2024-0001');
});

it('returns empty advisories for empty packages list', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->plainToken}",
    ])->postJson(
        "/{$this->organization->slug}/api/security-advisories",
        ['packages' => []],
    );

    $response->assertOk();
    expect($response->json('advisories'))->toBe([]);
});

it('omits packages with no advisories', function () {
    SecurityAdvisory::factory()->create([
        'package_name' => 'monolog/monolog',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->plainToken}",
    ])->postJson(
        "/{$this->organization->slug}/api/security-advisories",
        ['packages' => ['monolog/monolog', 'unknown/package']],
    );

    $response->assertOk();

    $data = $response->json('advisories');
    expect($data)->toHaveKey('monolog/monolog');
    expect($data)->not->toHaveKey('unknown/package');
});

it('requires authentication', function () {
    $response = $this->postJson(
        "/{$this->organization->slug}/api/security-advisories",
        ['packages' => ['monolog/monolog']],
    );

    $response->assertUnauthorized();
});
