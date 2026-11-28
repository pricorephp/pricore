<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePasskeyAuthenticator;

test('passkeys settings page requires password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.passkeys'))
        ->assertRedirect(route('password.confirm'));
});

test('passkeys settings page lists only the users own passkeys', function () {
    $user = User::factory()->create();
    $passkey = (new FakePasskeyAuthenticator)->register($user, 'MacBook');
    (new FakePasskeyAuthenticator)->register(User::factory()->create(), 'Someone else');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.passkeys'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/passkeys')
            ->has('passkeys', 1)
            ->where('passkeys.0.id', $passkey->id)
            ->where('passkeys.0.name', 'MacBook')
            ->where('passkeys.0.lastUsedAt', null)
        );
});

test('passkey registration options require password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('passkey.registration-options'))
        ->assertStatus(423);
});

test('passkey registration options identify the user and exclude existing passkeys', function () {
    $user = User::factory()->create();
    $passkey = (new FakePasskeyAuthenticator)->register($user);

    $options = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->json('options');

    expect($options['user']['name'])->toBe($user->email)
        ->and($options['user']['displayName'])->toBe($user->name)
        ->and($options['excludeCredentials'])->toHaveCount(1)
        ->and($options['excludeCredentials'][0]['id'])->toBe($passkey->credential_id);
});

test('users can remove their own passkey', function () {
    $user = User::factory()->create();
    $passkey = (new FakePasskeyAuthenticator)->register($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('settings.passkeys'))
        ->delete(route('passkey.destroy', $passkey))
        ->assertRedirect(route('settings.passkeys'))
        ->assertSessionHas('status', 'Passkey removed.');

    expect($user->passkeys()->exists())->toBeFalse();
});

test('users can not remove another users passkey', function () {
    $user = User::factory()->create();
    $passkey = (new FakePasskeyAuthenticator)->register(User::factory()->create());

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    expect($passkey->fresh())->not->toBeNull();
});

test('passkeys are removed with the user', function () {
    $user = User::factory()->create();
    $passkey = (new FakePasskeyAuthenticator)->register($user);

    $user->delete();

    expect($passkey->fresh())->toBeNull();
});
