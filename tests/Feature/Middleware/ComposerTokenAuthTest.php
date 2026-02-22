<?php

use App\Models\AccessToken;
use App\Models\Organization;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->plainToken = 'test-token-value-123';
    $this->accessToken = AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();
});

it('accepts a valid token in the password field of basic auth', function () {
    $credentials = base64_encode('token:'.$this->plainToken);

    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'Basic '.$credentials]
    )->assertSuccessful();
});

it('rejects a token in the username field only', function () {
    $credentials = base64_encode($this->plainToken.':');

    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'Basic '.$credentials]
    )->assertUnauthorized();
});

it('accepts a valid bearer token', function () {
    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'Bearer '.$this->plainToken]
    )->assertSuccessful();
});

it('returns 401 for missing authorization header', function () {
    $this->getJson(
        route('composer.packages.index', $this->organization),
    )->assertUnauthorized();
});

it('returns 401 for invalid authorization header', function () {
    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'InvalidScheme abc123']
    )->assertUnauthorized();
});

it('returns 401 for invalid basic auth encoding', function () {
    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'Basic !!!invalid-base64!!!']
    )->assertUnauthorized();
});

it('returns 401 for an expired token', function () {
    $expiredToken = 'expired-token-value';
    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($expiredToken)
        ->expired()
        ->create();

    $credentials = base64_encode('token:'.$expiredToken);

    $this->getJson(
        route('composer.packages.index', $this->organization),
        ['Authorization' => 'Basic '.$credentials]
    )->assertUnauthorized();
});
