<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageDownload;
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

function authenticatedPost(string $uri, array $data, string $token): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson($uri, $data);
}

it('records download notifications and returns 204', function () {
    $package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/package']);

    $response = authenticatedPost(
        "/{$this->organization->slug}/notify-batch",
        [
            'downloads' => [
                ['name' => 'acme/package', 'version' => '1.0.0'],
                ['name' => 'acme/package', 'version' => '2.0.0'],
            ],
        ],
        $this->plainToken,
    );

    $response->assertNoContent();

    expect(PackageDownload::count())->toBe(2);

    $download = PackageDownload::where('package_name', 'acme/package')
        ->where('version', '1.0.0')
        ->first();

    expect($download)
        ->organization_uuid->toBe($this->organization->uuid)
        ->package_uuid->toBe($package->uuid)
        ->package_name->toBe('acme/package');
});

it('records downloads for unknown packages with null package_uuid', function () {
    $response = authenticatedPost(
        "/{$this->organization->slug}/notify-batch",
        [
            'downloads' => [
                ['name' => 'unknown/package', 'version' => '1.0.0'],
            ],
        ],
        $this->plainToken,
    );

    $response->assertNoContent();

    $download = PackageDownload::first();

    expect($download)
        ->package_uuid->toBeNull()
        ->package_name->toBe('unknown/package')
        ->version->toBe('1.0.0');
});

it('validates that downloads array is required', function () {
    $response = authenticatedPost(
        "/{$this->organization->slug}/notify-batch",
        [],
        $this->plainToken,
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('downloads');
});

it('validates download entries have name and version', function () {
    $response = authenticatedPost(
        "/{$this->organization->slug}/notify-batch",
        [
            'downloads' => [
                ['name' => 'acme/package'],
            ],
        ],
        $this->plainToken,
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('downloads.0.version');
});

it('requires authentication', function () {
    $response = test()->postJson("/{$this->organization->slug}/notify-batch", [
        'downloads' => [
            ['name' => 'acme/package', 'version' => '1.0.0'],
        ],
    ]);

    $response->assertUnauthorized();
});
