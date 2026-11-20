<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['slug' => 'acme']);

    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken('metadata-cache-token')
        ->neverExpires()
        ->create();

    baseUrlServedSinceLongAgo();

    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package', 'updated_at' => now()->subDay()]);

    $this->version = PackageVersion::factory()
        ->for($this->package)
        ->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0', 'updated_at' => now()->subHour()]);

    $this->getMetadata = fn (array $headers = []): TestResponse => $this
        ->withHeaders(['Authorization' => 'Bearer metadata-cache-token', ...$headers])
        ->getJson('/acme/p2/acme/package.json');

    $this->versionLoads = 0;

    DB::listen(function ($query) {
        if (str_contains($query->sql, 'order by "released_at"')) {
            $this->versionLoads++;
        }
    });
});

it('builds the metadata once while the package does not change', function () {
    $first = ($this->getMetadata)()->assertOk();
    $second = ($this->getMetadata)()->assertOk();

    expect($this->versionLoads)->toBe(1)
        ->and($second->getContent())->toBe($first->getContent())
        ->and($second->headers->get('ETag'))->toBe($first->headers->get('ETag'))
        ->and($second->headers->get('Last-Modified'))->toBe($first->headers->get('Last-Modified'));
});

it('serves a version added after the metadata was cached', function () {
    ($this->getMetadata)()->assertJsonCount(1, 'packages.acme/package');

    PackageVersion::factory()
        ->for($this->package)
        ->create(['version' => '1.1.0', 'normalized_version' => '1.1.0.0']);

    ($this->getMetadata)()->assertJsonCount(2, 'packages.acme/package');
});

it('serves a changed version after the metadata was cached', function () {
    ($this->getMetadata)()->assertOk();

    $this->version->update(['composer_json' => ['name' => 'acme/package', 'description' => 'Changed']]);

    ($this->getMetadata)()->assertJsonPath('packages.acme/package.0.description', 'Changed');
});

it('serves the remaining versions after one is deleted', function () {
    $deletedVersion = PackageVersion::factory()
        ->for($this->package)
        ->create(['version' => '1.1.0', 'normalized_version' => '1.1.0.0', 'updated_at' => now()->subHour()]);

    ($this->getMetadata)()->assertJsonCount(2, 'packages.acme/package');

    $deletedVersion->delete();

    ($this->getMetadata)()->assertJsonCount(1, 'packages.acme/package');
});

it('does not cache metadata of a package that changed moments ago', function () {
    $this->version->update(['composer_json' => ['name' => 'acme/package', 'description' => 'First']]);

    ($this->getMetadata)()->assertJsonPath('packages.acme/package.0.description', 'First');

    // A write within the same second leaves every timestamp as it was
    PackageVersion::withoutTimestamps(
        fn () => $this->version->update(['composer_json' => ['name' => 'acme/package', 'description' => 'Second']])
    );

    ($this->getMetadata)()->assertJsonPath('packages.acme/package.0.description', 'Second');
});

it('answers If-Modified-Since without building the metadata', function () {
    $lastModified = ($this->getMetadata)()->headers->get('Last-Modified');

    $this->versionLoads = 0;
    cache()->flush();
    baseUrlServedSinceLongAgo();

    $response = ($this->getMetadata)(['If-Modified-Since' => $lastModified])->assertStatus(304);

    expect($this->versionLoads)->toBe(0)
        ->and($response->getContent())->toBe('')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600');
});

it('keeps stable and dev metadata apart', function () {
    PackageVersion::factory()
        ->for($this->package)
        ->create(['version' => 'dev-main', 'normalized_version' => 'dev-main', 'updated_at' => now()->subHour()]);

    ($this->getMetadata)()->assertJsonPath('packages.acme/package.0.version', '1.0.0');

    $this->withHeaders(['Authorization' => 'Bearer metadata-cache-token'])
        ->getJson('/acme/p2/acme/package~dev.json')
        ->assertOk()
        ->assertJsonCount(1, 'packages.acme/package')
        ->assertJsonPath('packages.acme/package.0.version', 'dev-main');
});
