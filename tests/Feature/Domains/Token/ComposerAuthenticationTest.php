<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'test-org',
        'owner_uuid' => $this->user->uuid,
    ]);

    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'test/package']);

    PackageVersion::factory()
        ->for($this->package)
        ->create(['version' => '1.0.0']);
});

it('returns 401 when no token is provided', function () {
    $response = getJson("/{$this->organization->slug}/packages.json");

    $response->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="Pricore"')
        ->assertJson(['message' => 'Unauthorized']);
});

it('returns 401 with invalid token', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid-token-12345',
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertUnauthorized();
});

it('returns 401 with expired token', function () {
    $plainToken = 'expired-token-'.uniqid();

    $expiredToken = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($plainToken)
        ->expired()
        ->create();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertUnauthorized();
});

it('returns 401 when token belongs to different organization', function () {
    $otherOrg = Organization::factory()->create(['slug' => 'other-org']);
    $plainToken = 'other-org-token-'.uniqid();

    AccessToken::factory()
        ->forOrganization($otherOrg)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertUnauthorized();
});

it('accepts valid Bearer authentication', function () {
    $plainToken = 'valid-bearer-token-'.uniqid();

    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertOk();
});

it('accepts valid Basic authentication with token as username', function () {
    $plainToken = 'valid-basic-token-'.uniqid();

    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $credentials = base64_encode("{$plainToken}:");

    $response = $this->withHeaders([
        'Authorization' => "Basic {$credentials}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertOk();
});

it('updates last_used_at timestamp when token is used', function () {
    $plainToken = 'timestamp-test-token-'.uniqid();

    $token = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create(['last_used_at' => null]);

    expect($token->last_used_at)->toBeNull();

    $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $token->refresh();

    expect($token->last_used_at)->not->toBeNull();
});

it('allows user-scoped token to access user organizations', function () {
    $plainToken = 'user-scoped-token-'.uniqid();

    // Add user as member of the organization
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => 'member',
    ]);

    AccessToken::factory()
        ->forUser($this->user)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertOk();
});

it('denies user-scoped token access to non-member organizations', function () {
    $otherUser = User::factory()->create();
    $plainToken = 'non-member-token-'.uniqid();

    AccessToken::factory()
        ->forUser($otherUser)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$plainToken}",
    ])->getJson("/{$this->organization->slug}/packages.json");

    $response->assertUnauthorized();
});
