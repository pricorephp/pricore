<?php

use App\Models\User;
use App\Models\UserGitCredential;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;

uses()->group('auth', 'github');

function mockSocialiteUser(array $attributes = []): SocialiteUser
{
    $defaults = [
        'id' => '12345678',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'nickname' => 'johndoe',
        'avatar' => 'https://avatars.githubusercontent.com/u/12345678',
        'token' => 'gho_test_token_abc123',
    ];

    $data = array_merge($defaults, $attributes);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($data['id']);
    $socialiteUser->shouldReceive('getName')->andReturn($data['name']);
    $socialiteUser->shouldReceive('getEmail')->andReturn($data['email']);
    $socialiteUser->shouldReceive('getNickname')->andReturn($data['nickname']);
    $socialiteUser->shouldReceive('getAvatar')->andReturn($data['avatar']);
    $socialiteUser->token = $data['token'];

    return $socialiteUser;
}

function mockSocialiteCallback(SocialiteUser $user): void
{
    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

function mockSocialiteRedirect(string $expectedUrl = 'https://github.com/login/oauth/authorize'): void
{
    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect($expectedUrl));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

test('redirect sends user to GitHub with only user:email scope', function () {
    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('scopes')
        ->once()
        ->with(['user:email'])
        ->andReturnSelf();
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://github.com/login/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $response = $this->get(route('auth.github.redirect'));

    $response->assertRedirect();
});

test('callback creates new user from GitHub', function () {
    $socialiteUser = mockSocialiteUser();
    mockSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->github_id)->toBe('12345678')
        ->and($user->github_nickname)->toBe('johndoe')
        ->and($user->avatar_url)->toBe('https://avatars.githubusercontent.com/u/12345678')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

test('callback logs in existing user matched by github_id', function () {
    $user = User::factory()->withGitHub()->create([
        'github_id' => '12345678',
    ]);

    $socialiteUser = mockSocialiteUser(['id' => '12345678', 'token' => 'gho_new_token']);
    mockSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->github_token)->toBe('gho_new_token');
});

test('callback links GitHub account to existing user matched by email', function () {
    $user = User::factory()->create(['email' => 'john@example.com']);

    expect($user->github_id)->toBeNull();

    $socialiteUser = mockSocialiteUser(['email' => 'john@example.com']);
    mockSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->github_id)->toBe('12345678')
        ->and($user->github_nickname)->toBe('johndoe');
});

test('callback handles null email gracefully', function () {
    $socialiteUser = mockSocialiteUser(['email' => null]);
    mockSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('callback handles GitHub authentication failure', function () {
    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('user')->andThrow(new \Exception('OAuth failed'));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('callback updates GitHub token on subsequent logins', function () {
    $user = User::factory()->withGitHub()->create([
        'github_id' => '12345678',
        'github_token' => 'gho_old_token',
    ]);

    $socialiteUser = mockSocialiteUser(['id' => '12345678', 'token' => 'gho_new_token']);
    mockSocialiteCallback($socialiteUser);

    $this->get(route('auth.github.callback'));

    $user->refresh();
    expect($user->github_token)->toBe('gho_new_token');
});

test('callback sets email_verified_at for new GitHub users', function () {
    $socialiteUser = mockSocialiteUser();
    mockSocialiteCallback($socialiteUser);

    $this->get(route('auth.github.callback'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

test('OAuth-only users have null password', function () {
    $socialiteUser = mockSocialiteUser();
    mockSocialiteCallback($socialiteUser);

    $this->get(route('auth.github.callback'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user->getAttributes()['password'])->toBeNull();
});

test('callback does not overwrite elevated git credentials on login', function () {
    $user = User::factory()->withGitHub()->create([
        'github_id' => '12345678',
    ]);

    $credential = UserGitCredential::factory()->github()->create([
        'user_uuid' => $user->uuid,
        'credentials' => ['token' => 'gho_elevated_repo_token'],
    ]);

    $socialiteUser = mockSocialiteUser(['id' => '12345678', 'token' => 'gho_login_only_token']);
    mockSocialiteCallback($socialiteUser);

    $this->get(route('auth.github.callback'));

    $credential->refresh();
    expect($credential->credentials['token'])->toBe('gho_elevated_repo_token');
});

test('callback uses nickname as name when GitHub name is null', function () {
    $socialiteUser = mockSocialiteUser(['name' => null, 'nickname' => 'johndoe']);
    mockSocialiteCallback($socialiteUser);

    $this->get(route('auth.github.callback'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user->name)->toBe('johndoe');
});

test('connect route requires authentication', function () {
    $response = $this->get(route('settings.github.connect'));

    $response->assertRedirect(route('login'));
});

test('connect route redirects to GitHub with repo scope', function () {
    $user = User::factory()->create();

    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('scopes')
        ->once()
        ->with(['repo', 'read:org'])
        ->andReturnSelf();
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://github.com/login/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $response = $this->actingAs($user)
        ->get(route('settings.github.connect'));

    $response->assertRedirect();
});

test('connect callback creates git credential for user', function () {
    $user = User::factory()->withGitHub()->create();

    $socialiteUser = mockSocialiteUser(['id' => $user->github_id, 'token' => 'gho_elevated_token']);
    mockSocialiteCallback($socialiteUser);

    $response = $this->actingAs($user)
        ->get(route('settings.github.callback'));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'GitHub credentials connected successfully.');

    $credential = UserGitCredential::where('user_uuid', $user->uuid)
        ->where('provider', 'github')
        ->first();

    expect($credential)->not->toBeNull()
        ->and($credential->credentials['token'])->toBe('gho_elevated_token');
});

test('connect callback updates existing credential', function () {
    $user = User::factory()->withGitHub()->create();

    UserGitCredential::factory()->github()->create([
        'user_uuid' => $user->uuid,
    ]);

    $socialiteUser = mockSocialiteUser(['id' => $user->github_id, 'token' => 'gho_elevated_token']);
    mockSocialiteCallback($socialiteUser);

    $response = $this->actingAs($user)
        ->get(route('settings.github.callback'));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'GitHub credentials updated successfully.');

    expect(UserGitCredential::where('user_uuid', $user->uuid)->count())->toBe(1);
});

test('callback without connect session performs normal login', function () {
    $user = User::factory()->withGitHub()->create();

    $socialiteUser = mockSocialiteUser(['id' => $user->github_id]);
    mockSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});
