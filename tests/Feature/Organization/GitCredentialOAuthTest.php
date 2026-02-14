<?php

use App\Models\User;
use App\Models\UserGitCredential;

uses()->group('settings', 'git-credentials');

beforeEach(function () {
    $this->user = User::factory()->withGitHub()->create();
});

test('manual credentials do not set source on user credential', function () {
    $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'github',
            'credentials' => ['token' => 'ghp_manual_token'],
        ]);

    $credential = UserGitCredential::where('user_uuid', $this->user->uuid)->first();

    expect($credential)->not->toBeNull()
        ->and($credential->credentials['token'])->toBe('ghp_manual_token');
});

test('git credentials index passes githubConnectUrl', function () {
    $response = $this->actingAs($this->user)
        ->get(route('settings.git-credentials'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/git-credentials')
        ->where('githubConnectUrl', route('auth.github.connect'))
    );
});

test('store requires credentials token for github provider', function () {
    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'github',
        ]);

    $response->assertSessionHasErrors('credentials.token');
});
