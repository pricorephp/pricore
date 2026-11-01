<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

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

it('treats a token without stored scopes as Composer-only', function () {
    $plain = 'legacy-'.Str::random(48);
    AccessToken::factory()->forUser($this->user)->withPlainToken($plain)->neverExpires()->create(['scopes' => null]);

    $this->withToken($plain)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")
        ->assertForbidden();
});

it('records last_used_at when a token authenticates', function () {
    $token = personalAccessToken($this->user);

    $this->withToken($token)->getJson('/api/v1/user')->assertOk();

    expect(AccessToken::query()->where('token_hash', hash('sha256', $token))->first()->last_used_at)
        ->not->toBeNull();
});

it('rejects unauthenticated requests before resolving the organization', function () {
    $this->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")->assertUnauthorized();
    $this->getJson('/api/v1/organizations/does-not-exist/repositories')->assertUnauthorized();
});

it('does not reveal the profile to tokens without API access', function () {
    $token = personalAccessToken($this->user, [TokenScope::Composer, TokenScope::WritePackages]);

    $this->withToken($token)->getJson('/api/v1/user')->assertForbidden();

    $this->withToken(personalAccessToken($this->user, [TokenScope::ReadPackages]))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('email', $this->user->email);
});
