<?php

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;

uses()->group('auth', 'security');

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
    Notification::fake();
    $this->secret = 'JBSWY3DPEHPK3PXP';
    $this->user = User::factory()->create([
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['test-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ]);
});

function mockTwoFactorOAuthCallback(User $user, string $provider): void
{
    $oauthUser = (new SocialiteUser)->map([
        'id' => 'two-factor-provider-id',
        'email' => $user->email,
        'name' => $user->name,
        'nickname' => 'two-factor-user',
        'avatar' => null,
    ])->setToken('test-oauth-token');
    $driver = Mockery::mock();
    $driver->shouldReceive('user')->once()->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

it('challenges OAuth users with enabled two factor before logging them in', function (string $provider, bool $linked) {
    if ($linked) {
        $this->user->update([$provider.'_id' => 'two-factor-provider-id']);
    }
    mockTwoFactorOAuthCallback($this->user, $provider);
    Event::fake([Login::class, TwoFactorAuthenticationChallenged::class]);

    $this->withSession(['url.intended' => route('settings.tokens.index')])
        ->get(route('auth.'.$provider.'.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $this->user->uuid)
        ->assertSessionHas('login.remember', true)
        ->assertSessionHas('url.intended', route('settings.tokens.index'));

    $this->assertGuest();
    Event::assertNotDispatched(Login::class);
    Event::assertDispatched(TwoFactorAuthenticationChallenged::class);
    $this->get(route('two-factor.login'))->assertOk();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
})->with(['github', 'gitlab'])->with([false, true]);

it('completes OAuth login through the existing code and recovery challenge', function (string $provider, string $method) {
    mockTwoFactorOAuthCallback($this->user, $provider);
    $invitation = OrganizationInvitation::factory()->create(['email' => $this->user->email]);
    $this->withSession([
        'url.intended' => route('settings.tokens.index'),
        'invitation_token' => $invitation->token,
    ])->get(route('auth.'.$provider.'.callback'))->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect($invitation->refresh()->accepted_at)->toBeNull();

    $payload = $method === 'code'
        ? ['code' => app(Google2FA::class)->getCurrentOtp($this->secret)]
        : ['recovery_code' => 'test-recovery-code'];

    $this->post(route('two-factor.login.store'), $payload)
        ->assertRedirect(route('settings.tokens.index'))
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember')
        ->assertCookie(Auth::guard()->getRecallerName());

    $this->assertAuthenticatedAs($this->user);
    expect($invitation->refresh()->accepted_at)->not->toBeNull();
    if ($method === 'recovery_code') {
        expect($this->user->refresh()->recoveryCodes())->not->toContain('test-recovery-code');
    }
})->with(['github', 'gitlab'])->with(['code', 'recovery_code']);

it('keeps OAuth users unauthenticated when the second factor is invalid', function (string $provider, string $method) {
    mockTwoFactorOAuthCallback($this->user, $provider);
    $this->get(route('auth.'.$provider.'.callback'))->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), [$method => 'invalid-code'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors($method)
        ->assertSessionHas('login.id', $this->user->uuid);

    $this->assertGuest();
})->with(['github', 'gitlab'])->with(['code', 'recovery_code']);

it('allows normal OAuth login when two factor is absent or not yet confirmed', function (string $provider, bool $hasSecret) {
    $this->user->update([
        'two_factor_secret' => $hasSecret ? encrypt($this->secret) : null,
        'two_factor_confirmed_at' => null,
    ]);
    mockTwoFactorOAuthCallback($this->user, $provider);

    $this->get(route('auth.'.$provider.'.callback'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
})->with(['github', 'gitlab'])->with([false, true]);

it('honors two factor configuration without a confirmation step', function (string $provider) {
    config(['fortify-options.two-factor-authentication.confirm' => false]);
    $this->user->update(['two_factor_confirmed_at' => null]);
    mockTwoFactorOAuthCallback($this->user, $provider);

    $this->get(route('auth.'.$provider.'.callback'))->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
})->with(['github', 'gitlab']);

it('makes the challenge accessible when an OAuth login callback arrives with an existing session', function (string $provider) {
    mockTwoFactorOAuthCallback($this->user, $provider);

    $this->actingAs(User::factory()->create())
        ->get(route('auth.'.$provider.'.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $this->user->uuid);

    $this->assertGuest();
    $this->get(route('two-factor.login'))->assertOk();
})->with(['github', 'gitlab']);
