<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

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

    Storage::fake('local');
});

function distGet(string $uri, string $token): TestResponse
{
    return test()->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->get($uri);
}

it('returns 404 for non-existent dist archive', function () {
    $response = distGet('/acme/dists/vendor/package/1.0.0/abc123.zip', $this->plainToken);

    $response->assertNotFound();
});

it('returns 404 when version has no dist_path', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123def456',
            'dist_url' => null,
            'dist_path' => null,
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.0.0/abc123def456.zip', $this->plainToken);

    $response->assertNotFound();
});

it('downloads a dist archive successfully', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $distPath = 'acme/acme/test-package/1.0.0_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/1.0.0/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.0.0/abc123def456.zip', $this->plainToken);

    $response->assertOk();
});

it('includes immutable caching headers on local dist downloads', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $distPath = 'acme/acme/test-package/1.0.0_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/1.0.0/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.0.0/abc123def456.zip', $this->plainToken);

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000', 'immutable', 'private');
    expect($response->headers->get('ETag'))->toBe('"abc123def456"');
});

it('requires authentication for dist download', function () {
    $response = test()->getJson('/acme/dists/acme/test-package/1.0.0/abc123.zip');

    $response->assertUnauthorized();
});

it('returns 404 when dist file is missing from disk', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.0.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/1.0.0/abc123def456.zip'),
            'dist_path' => 'acme/acme/test-package/1.0.0_abc123def456.zip',
            'dist_shasum' => 'abc123',
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.0.0/abc123def456.zip', $this->plainToken);

    $response->assertNotFound();
});
