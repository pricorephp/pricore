<?php

use App\Models\AccessToken;
use App\Models\DistArchive;
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

it('downloads a dist archive for a branch version with slashes', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    // A slash in the branch name becomes a directory separator in the archive
    // path, exactly as DistArchiveData::pathFor() writes it.
    $distPath = 'acme/acme/test-package/dev-feat/ISSUE-123-my-feature_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    $packageVersion = PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-feat/ISSUE-123-my-feature',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/dev-feat/ISSUE-123-my-feature/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    DistArchive::factory()->forPackageVersion($packageVersion)->create([
        'path' => $distPath,
        'shasum' => sha1('fake-zip-content'),
    ]);

    $response = distGet('/acme/dists/acme/test-package/dev-feat/ISSUE-123-my-feature/abc123def456.zip', $this->plainToken);

    $response->assertOk();
});

it('downloads a dist archive when requesting a v-prefixed version without the prefix', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $distPath = 'acme/acme/test-package/v1.2.0_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'v1.2.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/v1.2.0/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.2.0/abc123def456.zip', $this->plainToken);

    $response->assertOk();
});

it('downloads a dist archive when requesting an unprefixed version with a v prefix', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $distPath = 'acme/acme/test-package/1.2.0_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => '1.2.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/1.2.0/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/v1.2.0/abc123def456.zip', $this->plainToken);

    $response->assertOk();
});

it('returns 404 for a legacy version request with a non-matching reference', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $distPath = 'acme/acme/test-package/v1.2.0_abc123def456.zip';
    Storage::disk('local')->put($distPath, 'fake-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'v1.2.0',
            'source_reference' => 'abc123def456',
            'dist_url' => url('/acme/dists/acme/test-package/v1.2.0/abc123def456.zip'),
            'dist_path' => $distPath,
            'dist_shasum' => sha1('fake-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/1.2.0/0123456789ab.zip', $this->plainToken);

    $response->assertNotFound();
});

it('serves the archive for a commit the branch has already moved past', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    // The branch head has moved on, so the version row points at the new commit
    // while the archive built for the locked commit is still on disk.
    Storage::disk('local')->put('acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'old-zip-content');
    Storage::disk('local')->put('acme/acme/test-package/dev-main_bbbbbbbbbbbb.zip', 'new-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-main',
            'source_reference' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'dist_url' => url('/acme/dists/acme/test-package/dev-main/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.zip'),
            'dist_path' => 'acme/acme/test-package/dev-main_bbbbbbbbbbbb.zip',
            'dist_shasum' => sha1('new-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip', $this->plainToken);

    $response->assertOk();

    expect($response->streamedContent())->toBe('old-zip-content');
});

it('returns 404 when the archive for an older commit is gone from disk', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    Storage::disk('local')->put('acme/acme/test-package/dev-main_bbbbbbbbbbbb.zip', 'new-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-main',
            'source_reference' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'dist_url' => url('/acme/dists/acme/test-package/dev-main/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.zip'),
            'dist_path' => 'acme/acme/test-package/dev-main_bbbbbbbbbbbb.zip',
            'dist_shasum' => sha1('new-zip-content'),
        ]);

    $response = distGet('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip', $this->plainToken);

    $response->assertNotFound();
});

it('serves an archive whose version row no longer records a dist path', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    Storage::disk('local')->put('acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'old-zip-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-main',
            'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'dist_url' => null,
            'dist_path' => null,
            'dist_shasum' => null,
        ]);

    $response = distGet('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip', $this->plainToken);

    $response->assertOk();

    expect($response->streamedContent())->toBe('old-zip-content');
});

it('does not serve archives belonging to another organization', function () {
    $otherOrganization = Organization::factory()->create([
        'slug' => 'other',
        'owner_uuid' => $this->user->uuid,
    ]);

    $package = Package::factory()
        ->for($otherOrganization, 'organization')
        ->create(['name' => 'acme/test-package']);

    Storage::disk('local')->put('other/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'other-org-content');

    PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-main',
            'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'dist_path' => 'other/acme/test-package/dev-main_aaaaaaaaaaaa.zip',
        ]);

    $response = distGet('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip', $this->plainToken);

    $response->assertNotFound();
});

it('resolves through the archive row rather than the version pointer', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    Storage::disk('local')->put('acme/recorded.zip', 'recorded-content');
    Storage::disk('local')->put('acme/pointer.zip', 'pointer-content');

    $packageVersion = PackageVersion::factory()
        ->for($package)
        ->create([
            'version' => 'dev-main',
            'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'dist_path' => 'acme/pointer.zip',
            'dist_shasum' => sha1('pointer-content'),
        ]);

    DistArchive::factory()->forPackageVersion($packageVersion)->create([
        'path' => 'acme/recorded.zip',
        'shasum' => sha1('recorded-content'),
    ]);

    $response = distGet('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip', $this->plainToken);

    $response->assertOk();

    expect($response->streamedContent())->toBe('recorded-content');
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
