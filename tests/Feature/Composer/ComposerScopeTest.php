<?php

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['slug' => 'acme']);
});

it('allows registry access for a token with the composer scope', function () {
    $token = organizationAccessToken($this->organization, [TokenScope::Composer]);

    $this->withToken($token)
        ->getJson('/acme/packages.json')
        ->assertOk();
});

it('allows registry access for a legacy null-scope token', function () {
    $token = organizationAccessToken($this->organization, scopes: null);

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
