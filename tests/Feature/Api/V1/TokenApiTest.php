<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    joinOrganization($this->organization, $this->user, OrganizationRole::Owner);
});

it('creates an organization token with scopes and returns the plaintext once', function () {
    // A full-access (legacy) personal token may grant any scope.
    $token = personalAccessToken($this->user, scopes: null);

    $response = $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/tokens", [
            'name' => 'CI token',
            'scopes' => ['composer', 'read:packages'],
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'CI token')
        ->assertJsonPath('scopes', ['composer', 'read:packages']);

    expect($response->json('plainToken'))->toBeString()->not->toBeEmpty();

    assertDatabaseHas('access_tokens', [
        'organization_uuid' => $this->organization->uuid,
        'name' => 'CI token',
    ]);
});

it('defaults new tokens to composer scope when none are given', function () {
    $token = personalAccessToken($this->user, scopes: null);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/tokens", ['name' => 'Composer'])
        ->assertCreated()
        ->assertJsonPath('scopes', ['composer']);
});

it('prevents a scoped token from granting scopes it does not hold', function () {
    // This token can write tokens, but does not itself hold delete:packages.
    $token = personalAccessToken($this->user, [TokenScope::WriteTokens, TokenScope::ReadPackages]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/tokens", [
            'name' => 'Escalated',
            'scopes' => ['delete:packages'],
        ])
        ->assertForbidden();
});

it('creates a personal access token via the user endpoint', function () {
    $token = personalAccessToken($this->user, scopes: null);

    $response = $this->withToken($token)
        ->postJson('/api/v1/user/tokens', [
            'name' => 'My laptop',
            'scopes' => ['composer'],
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'My laptop');

    expect($response->json('plainToken'))->toBeString()->not->toBeEmpty();

    assertDatabaseHas('access_tokens', [
        'user_uuid' => $this->user->uuid,
        'name' => 'My laptop',
    ]);
});

it('rejects invalid scopes', function () {
    $token = personalAccessToken($this->user, scopes: null);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/tokens", [
            'name' => 'Bad',
            'scopes' => ['not-a-real-scope'],
        ])
        ->assertStatus(422);
});

it('updates an organization token name and scopes', function () {
    $token = personalAccessToken($this->user, scopes: null);
    $target = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withScopes([TokenScope::Composer])
        ->create();

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/tokens/{$target->uuid}", [
            'name' => 'Renamed',
            'scopes' => ['composer', 'write:repositories'],
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('scopes', ['composer', 'write:repositories']);
});

it('keeps existing scopes on a name-only update', function () {
    $token = personalAccessToken($this->user, scopes: null);
    $target = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withScopes([TokenScope::Composer, TokenScope::ReadPackages])
        ->create();

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/tokens/{$target->uuid}", [
            'name' => 'Just renamed',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Just renamed')
        ->assertJsonPath('scopes', ['composer', 'read:packages']);
});

it('prevents granting scopes the caller lacks on update', function () {
    $token = personalAccessToken($this->user, [
        TokenScope::WriteTokens,
        TokenScope::ReadPackages,
    ]);
    $target = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withScopes([TokenScope::Composer])
        ->create();

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/tokens/{$target->uuid}", [
            'name' => 'Escalate',
            'scopes' => ['delete:packages'],
        ])
        ->assertForbidden();
});
