<?php

use App\Models\User;
use App\Models\UserGitCredential;

uses()->group('settings', 'git-credentials');

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('index page shows user git credentials', function () {
    UserGitCredential::factory()->github()->create([
        'user_uuid' => $this->user->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('settings.git-credentials'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/git-credentials')
        ->has('credentials', 1)
        ->has('providers')
        ->has('githubConnectUrl')
    );
});

test('index page shows empty credentials for new user', function () {
    $response = $this->actingAs($this->user)
        ->get(route('settings.git-credentials'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/git-credentials')
        ->has('credentials', 0)
    );
});

test('store creates a new git credential', function () {
    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'github',
            'credentials' => ['token' => 'ghp_test_token'],
        ]);

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'Git credentials added successfully.');

    $credential = UserGitCredential::where('user_uuid', $this->user->uuid)->first();
    expect($credential)->not->toBeNull()
        ->and($credential->provider->value)->toBe('github')
        ->and($credential->credentials['token'])->toBe('ghp_test_token');
});

test('store prevents duplicate provider credentials', function () {
    UserGitCredential::factory()->github()->create([
        'user_uuid' => $this->user->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'github',
            'credentials' => ['token' => 'ghp_another_token'],
        ]);

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('error');

    expect(UserGitCredential::where('user_uuid', $this->user->uuid)->count())->toBe(1);
});

test('store requires credentials token for github provider', function () {
    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'github',
        ]);

    $response->assertSessionHasErrors('credentials.token');
});

test('store requires credentials for bitbucket provider', function () {
    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'bitbucket',
        ]);

    $response->assertSessionHasErrors(['credentials.username', 'credentials.app_password']);
});

test('store requires ssh key for git provider', function () {
    $response = $this->actingAs($this->user)
        ->post(route('settings.git-credentials.store'), [
            'provider' => 'git',
        ]);

    $response->assertSessionHasErrors('credentials.ssh_key');
});

test('update modifies existing credential', function () {
    $credential = UserGitCredential::factory()->github()->create([
        'user_uuid' => $this->user->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->patch(route('settings.git-credentials.update', $credential), [
            'credentials' => ['token' => 'ghp_updated_token'],
        ]);

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'Git credentials updated successfully.');

    $credential->refresh();
    expect($credential->credentials['token'])->toBe('ghp_updated_token');
});

test('update rejects other users credentials', function () {
    $otherUser = User::factory()->create();
    $credential = UserGitCredential::factory()->github()->create([
        'user_uuid' => $otherUser->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->patch(route('settings.git-credentials.update', $credential), [
            'credentials' => ['token' => 'ghp_hacked'],
        ]);

    $response->assertForbidden();
});

test('destroy deletes credential', function () {
    $credential = UserGitCredential::factory()->github()->create([
        'user_uuid' => $this->user->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('settings.git-credentials.destroy', $credential));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'Git credentials removed successfully.');

    expect(UserGitCredential::find($credential->uuid))->toBeNull();
});

test('destroy rejects other users credentials', function () {
    $otherUser = User::factory()->create();
    $credential = UserGitCredential::factory()->github()->create([
        'user_uuid' => $otherUser->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('settings.git-credentials.destroy', $credential));

    $response->assertForbidden();

    expect(UserGitCredential::find($credential->uuid))->not->toBeNull();
});

test('unauthenticated user cannot access git credentials', function () {
    $response = $this->get(route('settings.git-credentials'));

    $response->assertRedirect(route('login'));
});
