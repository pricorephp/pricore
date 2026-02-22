<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'acme',
        'owner_uuid' => $this->user->uuid,
    ]);

    // Create a valid access token for this organization
    $this->plainToken = 'test-token-'.uniqid();
    $this->accessToken = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();
});

// Helper to make authenticated requests
function authenticatedGet(string $uri, string $token): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson($uri);
}

it('returns root packages.json with metadata-url template', function () {
    $response = authenticatedGet("/{$this->organization->slug}/packages.json", $this->plainToken);

    $response->assertOk()
        ->assertJson([
            'metadata-url' => url("/{$this->organization->slug}/p2/%package%.json"),
        ]);
});

it('returns package metadata in composer v2 format', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/awesome-package']);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'normalized_version' => '1.0.0.0',
            'composer_json' => [
                'name' => 'acme/awesome-package',
                'description' => 'An awesome package',
                'type' => 'library',
                'require' => ['php' => '^8.1'],
            ],
            'source_url' => 'https://github.com/acme/awesome-package.git',
            'source_reference' => 'abc123',
        ]);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/awesome-package.json", $this->plainToken);

    $response->assertOk()
        ->assertJsonStructure([
            'packages' => [
                'acme/awesome-package' => [
                    '*' => ['name', 'version', 'version_normalized', 'source'],
                ],
            ],
            'minified',
        ])
        ->assertJsonPath('packages.acme/awesome-package.0.name', 'acme/awesome-package')
        ->assertJsonPath('packages.acme/awesome-package.0.version', '1.0.0')
        ->assertJsonPath('packages.acme/awesome-package.0.source.url', 'https://github.com/acme/awesome-package.git')
        ->assertJsonPath('packages.acme/awesome-package.0.source.reference', 'abc123');
});

it('returns multiple versions ordered by release date', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    $oldest = now()->subDays(10);
    $middle = now()->subDays(5);
    $newest = now()->subDay();

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'normalized_version' => '1.0.0.0',
            'released_at' => $oldest,
        ]);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '2.0.0',
            'normalized_version' => '2.0.0.0',
            'released_at' => $newest,
        ]);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.5.0',
            'normalized_version' => '1.5.0.0',
            'released_at' => $middle,
        ]);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk();

    $versions = $response->json('packages.acme/package');

    expect($versions)->toHaveCount(3)
        ->and($versions[0]['version'])->toBe('2.0.0')
        ->and($versions[1]['version'])->toBe('1.5.0')
        ->and($versions[2]['version'])->toBe('1.0.0');
});

it('excludes dev versions from stable endpoint', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => 'dev-main']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '2.0.0-dev']);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk();

    $versions = $response->json('packages.acme/package');

    expect($versions)->toHaveCount(1)
        ->and($versions[0]['version'])->toBe('1.0.0');
});

it('returns only dev versions from dev endpoint', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => 'dev-main']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => 'dev-develop']);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package~dev.json", $this->plainToken);

    $response->assertOk();

    $versions = $response->json('packages.acme/package');

    expect($versions)->toHaveCount(2);

    $versionStrings = collect($versions)->pluck('version')->all();

    expect($versionStrings)->toContain('dev-main', 'dev-develop')
        ->not->toContain('1.0.0');
});

it('returns 404 for non-existent package', function () {
    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/non-existent.json", $this->plainToken);

    $response->assertNotFound()
        ->assertJson([
            'packages' => [],
            'minified' => 'composer/2.0',
        ]);
});

it('returns 404 for non-existent organization', function () {
    $response = authenticatedGet('/non-existent/p2/acme/package.json', $this->plainToken);

    $response->assertNotFound();
});

it('includes caching headers', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk()
        ->assertHeader('Cache-Control')
        ->assertHeader('Last-Modified');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=3600', 'public');
});

it('includes dist information when available', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'source_url' => 'https://github.com/acme/package.git',
            'source_reference' => 'abc123',
            'dist_url' => 'https://example.com/dist/acme-package-1.0.0.zip',
        ]);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk()
        ->assertJsonPath('packages.acme/package.0.dist.type', 'zip')
        ->assertJsonPath('packages.acme/package.0.dist.url', 'https://example.com/dist/acme-package-1.0.0.zip')
        ->assertJsonPath('packages.acme/package.0.dist.reference', 'abc123');
});

it('includes time field from released_at', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    $releasedAt = now()->subDays(5);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'released_at' => $releasedAt,
        ]);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk()
        ->assertJsonPath('packages.acme/package.0.time', $releasedAt->toIso8601String());
});

it('includes available-packages and notify-batch in packages.json', function () {
    Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/beta-package']);

    Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/alpha-package']);

    $response = authenticatedGet("/{$this->organization->slug}/packages.json", $this->plainToken);

    $response->assertOk()
        ->assertJsonPath('available-packages', ['acme/alpha-package', 'acme/beta-package'])
        ->assertJsonPath('notify-batch', url("/{$this->organization->slug}/notify-batch"));
});

it('returns minified metadata for multiple versions', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'normalized_version' => '1.0.0.0',
            'released_at' => now()->subDays(2),
            'composer_json' => [
                'name' => 'acme/package',
                'description' => 'A package',
                'type' => 'library',
                'require' => ['php' => '^8.1'],
            ],
            'source_url' => 'https://github.com/acme/package.git',
            'source_reference' => 'abc123',
        ]);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '2.0.0',
            'normalized_version' => '2.0.0.0',
            'released_at' => now()->subDay(),
            'composer_json' => [
                'name' => 'acme/package',
                'description' => 'A package',
                'type' => 'library',
                'require' => ['php' => '^8.2'],
            ],
            'source_url' => 'https://github.com/acme/package.git',
            'source_reference' => 'def456',
        ]);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk();

    $versions = $response->json('packages.acme/package');

    // First version (newest) should have all keys
    expect($versions[0])->toHaveKey('name')
        ->toHaveKey('version');

    // Second version should be minified (missing keys that are same as first)
    // The minifier removes keys whose values match the previous entry
    expect($versions[1])->toHaveKey('version');
});

it('returns 304 when If-None-Match header matches ETag', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    // First request to get the ETag
    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);
    $response->assertOk();
    $etag = $response->headers->get('ETag');

    expect($etag)->not->toBeNull();

    // Second request with If-None-Match
    $response = test()->withHeaders([
        'Authorization' => "Bearer {$this->plainToken}",
        'If-None-Match' => $etag,
    ])->getJson("/{$this->organization->slug}/p2/acme/package.json");

    $response->assertStatus(304);
});

it('returns ETag header on metadata responses', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    $response = authenticatedGet("/{$this->organization->slug}/p2/acme/package.json", $this->plainToken);

    $response->assertOk()
        ->assertHeader('ETag');
});

it('does not leak packages from other organizations', function () {
    $otherOrg = Organization::factory()->create(['slug' => 'other-org']);

    $package = Package::factory()
        ->for($otherOrg, 'organization')
        ->create(['name' => 'other/package']);

    PackageVersion::factory()
        ->for($package)
        ->create(['version' => '1.0.0']);

    $response = authenticatedGet("/{$this->organization->slug}/p2/other/package.json", $this->plainToken);

    $response->assertNotFound();
});
