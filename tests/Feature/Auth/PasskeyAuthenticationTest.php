<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePasskeyAuthenticator;

test('login screen enables passkeys', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('passkeysEnabled', true)
        );
});

test('users can sign in with a passkey without a two factor challenge', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $passkey = $fakePasskeyAuthenticator->register($user);

    $options = $this->getJson(route('passkey.login-options'))
        ->assertOk()
        ->json('options');

    $this->postJson(route('passkey.login'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle()),
        'remember' => true,
    ])
        ->assertOk()
        ->assertJson(['redirect' => url('/')]);

    $this->assertAuthenticatedAs($user);
    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($passkey->refresh()->last_used_at)->not->toBeNull();
});

test('users can not sign in with an invalid passkey signature', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user);

    $options = $this->getJson(route('passkey.login-options'))->json('options');
    $credential = $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle());
    $credential['response']['signature'] = (new FakePasskeyAuthenticator)->assert($options, $user->getPasskeyUserHandle())['response']['signature'];

    $this->postJson(route('passkey.login'), ['credential' => $credential])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential' => 'This passkey could not be verified.']);

    $this->assertGuest();
});

test('users can not sign in with a passkey that skipped device verification', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user);

    $options = $this->getJson(route('passkey.login-options'))->json('options');

    $this->postJson(route('passkey.login'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle(), userVerified: false),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credential');

    $this->assertGuest();
});

test('users can not sign in with a passkey assertion from another origin', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user);

    $options = $this->getJson(route('passkey.login-options'))->json('options');

    $this->postJson(route('passkey.login'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle(), origin: 'https://evil.example'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credential');

    $this->assertGuest();
});

test('users can not sign in with a passkey that was removed', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user)->delete();

    $options = $this->getJson(route('passkey.login-options'))->json('options');

    $this->postJson(route('passkey.login'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle()),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credential');

    $this->assertGuest();
});

test('a passkey challenge can only be used once', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user);

    $options = $this->getJson(route('passkey.login-options'))->json('options');
    $credential = $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle());

    $this->postJson(route('passkey.login'), ['credential' => $credential])->assertOk();

    auth()->logout();

    $this->postJson(route('passkey.login'), ['credential' => $credential])
        ->assertUnprocessable();

    $this->assertGuest();
});

test('confirm password screen offers passkeys to users that have one', function () {
    $user = User::factory()->create();
    (new FakePasskeyAuthenticator)->register($user);

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/confirm-password')
            ->where('hasPasskeys', true)
        );
});

test('users can confirm their password with a passkey', function () {
    $user = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($user);

    $options = $this->actingAs($user)
        ->getJson(route('passkey.confirm-options'))
        ->assertOk()
        ->json('options');

    $this->postJson(route('passkey.confirm'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $user->getPasskeyUserHandle()),
    ])->assertOk();

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});

test('users can not confirm their password with another users passkey', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $fakePasskeyAuthenticator = new FakePasskeyAuthenticator;
    $fakePasskeyAuthenticator->register($otherUser);

    $options = $this->actingAs($user)
        ->getJson(route('passkey.confirm-options'))
        ->json('options');

    $this->postJson(route('passkey.confirm'), [
        'credential' => $fakePasskeyAuthenticator->assert($options, $otherUser->getPasskeyUserHandle()),
    ])->assertUnprocessable();

    expect(session('auth.password_confirmed_at'))->toBeNull();
});
