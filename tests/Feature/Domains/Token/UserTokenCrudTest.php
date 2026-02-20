<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'owner_uuid' => $this->user->uuid,
    ]);
});

it('requires authentication to access tokens page', function () {
    assertGuest();

    get('/settings/tokens')
        ->assertRedirect('/login');
});

it('lists user-scoped tokens on the settings page', function () {
    $token = AccessToken::factory()
        ->forUser($this->user)
        ->neverExpires()
        ->create(['name' => 'My Personal Token']);

    actingAs($this->user)
        ->get('/settings/tokens')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/tokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'My Personal Token')
        );
});

it('does not list org-scoped tokens on the user settings page', function () {
    AccessToken::factory()
        ->forOrganization($this->organization)
        ->neverExpires()
        ->create(['name' => 'Org Token']);

    AccessToken::factory()
        ->forUser($this->user)
        ->neverExpires()
        ->create(['name' => 'User Token']);

    actingAs($this->user)
        ->get('/settings/tokens')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('tokens', 1)
            ->where('tokens.0.name', 'User Token')
        );
});

it('creates a user-scoped token', function () {
    actingAs($this->user)
        ->post('/settings/tokens', [
            'name' => 'New Personal Token',
            'expires_at' => 'never',
        ])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/tokens')
            ->has('tokenCreated')
            ->where('tokenCreated.name', 'New Personal Token')
        );

    $token = AccessToken::query()
        ->where('user_uuid', $this->user->uuid)
        ->where('name', 'New Personal Token')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->organization_uuid)->toBeNull()
        ->and($token->user_uuid)->toBe($this->user->uuid);
});

it('revokes own user-scoped token', function () {
    $token = AccessToken::factory()
        ->forUser($this->user)
        ->neverExpires()
        ->create(['name' => 'Token to Revoke']);

    actingAs($this->user)
        ->delete("/settings/tokens/{$token->uuid}")
        ->assertRedirect();

    expect(AccessToken::find($token->uuid))->toBeNull();
});

it('cannot revoke another user\'s token', function () {
    $otherUser = User::factory()->create();

    $token = AccessToken::factory()
        ->forUser($otherUser)
        ->neverExpires()
        ->create(['name' => 'Other User Token']);

    actingAs($this->user)
        ->delete("/settings/tokens/{$token->uuid}")
        ->assertForbidden();

    expect(AccessToken::find($token->uuid))->not->toBeNull();
});

it('requires authentication to create a token', function () {
    assertGuest();

    post('/settings/tokens', [
        'name' => 'Token',
        'expires_at' => 'never',
    ])->assertRedirect('/login');
});

it('requires authentication to revoke a token', function () {
    $token = AccessToken::factory()
        ->forUser($this->user)
        ->neverExpires()
        ->create();

    assertGuest();

    delete("/settings/tokens/{$token->uuid}")
        ->assertRedirect('/login');
});
