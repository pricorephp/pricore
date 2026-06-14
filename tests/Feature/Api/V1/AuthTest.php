<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    joinOrganization($this->organization, $this->user, OrganizationRole::Owner);
});

it('authenticates a valid personal access token', function () {
    $token = personalAccessToken($this->user);

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJson([
            'uuid' => $this->user->uuid,
            'email' => $this->user->email,
        ]);
});

it('rejects a request without an Authorization header', function () {
    $this->getJson('/api/v1/user')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('rejects an unknown token', function () {
    $this->withToken('does-not-exist')
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

it('rejects an expired token', function () {
    $plain = 'pat-expired-token';
    AccessToken::factory()->forUser($this->user)->withPlainToken($plain)->expired()->create();

    $this->withToken($plain)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

it('treats a null-scope (legacy) token as having full access', function () {
    $token = personalAccessToken($this->user, scopes: null);

    // read:repositories is required, but a legacy token implicitly carries every scope.
    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")
        ->assertOk();
});

it('records last_used_at when a token authenticates', function () {
    $token = personalAccessToken($this->user);

    $this->withToken($token)->getJson('/api/v1/user')->assertOk();

    expect(AccessToken::query()->where('token_hash', hash('sha256', $token))->first()->last_used_at)
        ->not->toBeNull();
});
