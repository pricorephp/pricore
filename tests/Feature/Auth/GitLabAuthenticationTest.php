<?php

use App\Domains\Auth\Contracts\Enums\GitLabOAuthIntent;
use App\Models\User;
use App\Models\UserGitCredential;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\GitLab\Provider as GitLabSocialiteProvider;

uses()->group('auth', 'gitlab');

function mockGitLabSocialiteUser(array $attributes = []): SocialiteUser
{
    $defaults = [
        'id' => '87654321',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'nickname' => 'janedoe',
        'avatar' => 'https://gitlab.com/uploads/-/system/user/avatar/87654321/avatar.png',
        'token' => 'glpat-test_token_abc123',
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

function mockGitLabSocialiteCallback(SocialiteUser $user): void
{
    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);
}

function mockGitLabSocialiteRedirect(): void
{
    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://gitlab.com/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);
}

test('redirect sends user to GitLab with read_user scope', function () {
    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('scopes')
        ->once()
        ->with(['read_user'])
        ->andReturnSelf();
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://gitlab.com/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);

    $response = $this->get(route('auth.gitlab.redirect'));

    $response->assertRedirect();
    $response->assertSessionHas('gitlab_oauth_intent', GitLabOAuthIntent::Login);
});

test('callback creates new user from GitLab', function () {
    config()->set('fortify.sign_up_enabled', true);

    $socialiteUser = mockGitLabSocialiteUser();
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Login])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->gitlab_id)->toBe('87654321')
        ->and($user->gitlab_nickname)->toBe('janedoe')
        ->and($user->avatar_url)->toBe('https://gitlab.com/uploads/-/system/user/avatar/87654321/avatar.png')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

test('callback logs in existing user matched by gitlab_id', function () {
    $user = User::factory()->withGitLab()->create([
        'gitlab_id' => '87654321',
    ]);

    $socialiteUser = mockGitLabSocialiteUser(['id' => '87654321', 'token' => 'glpat-new_token']);
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->gitlab_token)->toBe('glpat-new_token');
});

test('callback links GitLab account to existing user matched by email', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    expect($user->gitlab_id)->toBeNull();

    $socialiteUser = mockGitLabSocialiteUser(['email' => 'jane@example.com']);
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->gitlab_id)->toBe('87654321')
        ->and($user->gitlab_nickname)->toBe('janedoe');
});

test('callback handles null email gracefully', function () {
    $socialiteUser = mockGitLabSocialiteUser(['email' => null]);
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('callback handles GitLab authentication failure', function () {
    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('OAuth failed'));

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);

    $response = $this->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('callback handles GitLab authentication failure during connect', function () {
    $user = User::factory()->create();

    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('OAuth failed'));

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);

    $response = $this->actingAs($user)
        ->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Connect])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('error');
});

test('callback blocks new GitLab user when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $socialiteUser = mockGitLabSocialiteUser();
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Login])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Registration is currently closed. You need an invitation to create an account.');
    $this->assertGuest();

    expect(User::where('email', 'jane@example.com')->exists())->toBeFalse();
});

test('callback allows new GitLab user with invitation token when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $socialiteUser = mockGitLabSocialiteUser();
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->withSession([
        'gitlab_oauth_intent' => GitLabOAuthIntent::Login,
        'invitation_token' => 'test-token',
    ])->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('connect route requires authentication', function () {
    $response = $this->get(route('settings.gitlab.connect'));

    $response->assertRedirect(route('login'));
});

test('connect route redirects to GitLab with api scope', function () {
    $user = User::factory()->create();

    $provider = Mockery::mock(GitLabSocialiteProvider::class);
    $provider->shouldReceive('scopes')
        ->once()
        ->with(['api'])
        ->andReturnSelf();
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://gitlab.com/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($provider);

    $response = $this->actingAs($user)
        ->get(route('settings.gitlab.connect'));

    $response->assertRedirect();
    $response->assertSessionHas('gitlab_oauth_intent', GitLabOAuthIntent::Connect);
});

test('connect callback creates git credential for user', function () {
    $user = User::factory()->withGitLab()->create();

    $socialiteUser = mockGitLabSocialiteUser(['id' => $user->gitlab_id, 'token' => 'glpat-elevated_token']);
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Connect])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'GitLab credentials connected successfully.');

    $credential = UserGitCredential::where('user_uuid', $user->uuid)
        ->where('provider', 'gitlab')
        ->first();

    expect($credential)->not->toBeNull()
        ->and($credential->credentials['token'])->toBe('glpat-elevated_token');
});

test('connect callback updates existing credential', function () {
    $user = User::factory()->withGitLab()->create();

    UserGitCredential::factory()->gitlab()->create([
        'user_uuid' => $user->uuid,
    ]);

    $socialiteUser = mockGitLabSocialiteUser(['id' => $user->gitlab_id, 'token' => 'glpat-elevated_token']);
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Connect])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('settings.git-credentials'));
    $response->assertSessionHas('status', 'GitLab credentials updated successfully.');

    expect(UserGitCredential::where('user_uuid', $user->uuid)->count())->toBe(1);
});

test('connect callback without auth redirects to login', function () {
    $socialiteUser = mockGitLabSocialiteUser();
    mockGitLabSocialiteCallback($socialiteUser);

    $response = $this->withSession(['gitlab_oauth_intent' => GitLabOAuthIntent::Connect])
        ->get(route('auth.gitlab.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});
