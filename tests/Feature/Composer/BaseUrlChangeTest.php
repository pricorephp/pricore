<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['slug' => 'acme']);

    $this->plainToken = 'test-token-'.uniqid();
    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();

    $package = Package::factory()->for($this->organization, 'organization')->create([
        'name' => 'acme/app',
        'updated_at' => now()->subDays(30),
    ]);

    PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_reference' => 'abc123',
        'dist_url' => 'https://old-host.example.com/acme/dists/acme/app/1.0.0/abc123.zip',
        'dist_shasum' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
        'updated_at' => now()->subDays(30),
    ]);

    $this->getFrom = fn (string $url, array $headers = []) => $this->withToken($this->plainToken)
        ->withHeaders($headers)
        ->getJson($url);
});

it('serves dist urls under the current base url instead of the stored host', function () {
    ($this->getFrom)('https://new-host.example.com/acme/p2/acme/app.json')
        ->assertOk()
        ->assertJsonPath('packages.acme/app.0.dist.url', 'https://new-host.example.com/acme/dists/acme/app/1.0.0/abc123.zip');
});

it('does not return 304 for package metadata cached under a previous base url', function () {
    $lastModified = ($this->getFrom)('https://old-host.example.com/acme/p2/acme/app.json')
        ->assertOk()
        ->headers->get('Last-Modified');

    $this->travel(1)->hour();

    ($this->getFrom)('https://old-host.example.com/acme/p2/acme/app.json', ['If-Modified-Since' => $lastModified])
        ->assertNotModified();

    ($this->getFrom)('https://new-host.example.com/acme/p2/acme/app.json', ['If-Modified-Since' => $lastModified])
        ->assertOk()
        ->assertJsonPath('packages.acme/app.0.dist.url', 'https://new-host.example.com/acme/dists/acme/app/1.0.0/abc123.zip');
});

it('does not return 304 for packages.json cached under a previous base url', function () {
    $lastModified = ($this->getFrom)('https://old-host.example.com/acme/packages.json')
        ->assertOk()
        ->headers->get('Last-Modified');

    $this->travel(1)->hour();

    ($this->getFrom)('https://old-host.example.com/acme/packages.json', ['If-Modified-Since' => $lastModified])
        ->assertNotModified();

    ($this->getFrom)('https://new-host.example.com/acme/packages.json', ['If-Modified-Since' => $lastModified])
        ->assertOk()
        ->assertJsonPath('metadata-url', 'https://new-host.example.com/acme/p2/%package%.json');
});
