<?php

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['slug' => 'acme']);
});

it('allows registry access for a token with the composer scope', function () {
    $token = organizationAccessToken($this->organization, [TokenScope::Composer]);

    $this->withToken($token)
        ->getJson('/acme/packages.json')
        ->assertOk();
});

it('allows registry access for a token without stored scopes', function () {
    $plain = 'legacy-'.Str::random(48);
    AccessToken::factory()->forOrganization($this->organization)->withPlainToken($plain)->neverExpires()->create(['scopes' => null]);
    $token = $plain;

    $this->withToken($token)
        ->getJson('/acme/packages.json')
        ->assertOk();
});

it('forbids registry access for a token without the composer scope', function () {
    $token = organizationAccessToken($this->organization, [TokenScope::ReadRepositories]);

    $this->withToken($token)
        ->getJson('/acme/packages.json')
        ->assertForbidden();
});
