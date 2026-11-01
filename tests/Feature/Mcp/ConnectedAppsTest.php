<?php

use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

use function Pest\Laravel\actingAs;

uses()->group('mcp', 'settings');

function issueMcpToken(User $user, Client $client, ?DateTimeInterface $expiresAt = null, bool $revoked = false): Token
{
    return Token::query()->create([
        'id' => Str::random(80),
        'user_id' => $user->uuid,
        'client_id' => $client->getKey(),
        'scopes' => [Registrar::OAUTH_SCOPE],
        'revoked' => $revoked,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->asPublic()->create(['name' => 'Claude Code']);
});

it('lists the apps that still hold a valid token', function () {
    issueMcpToken($this->user, $this->client);

    $expiredClient = Client::factory()->asPublic()->create(['name' => 'Cursor']);
    $expired = issueMcpToken($this->user, $expiredClient, now()->subHour());
    RefreshToken::query()->create(['id' => Str::random(80), 'access_token_id' => $expired->id, 'revoked' => false, 'expires_at' => now()->addDays(10)]);

    $revokedClient = Client::factory()->asPublic()->create(['name' => 'Old app']);
    issueMcpToken($this->user, $revokedClient, revoked: true);

    issueMcpToken(User::factory()->create(), Client::factory()->asPublic()->create(['name' => 'Someone else']));

    actingAs($this->user)
        ->get(route('settings.connected-apps.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/connected-apps')
            ->has('connectedApps', 2)
            ->where('connectedApps', fn ($connectedApps) => collect($connectedApps)->pluck('name')->sort()->values()->all() === ['Claude Code', 'Cursor'])
            ->where('mcpUrl', url('/mcp')));
});

it('revokes the access and refresh tokens of an app', function () {
    $token = issueMcpToken($this->user, $this->client);
    $refreshToken = RefreshToken::query()->create(['id' => Str::random(80), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDays(30)]);

    actingAs($this->user)
        ->delete(route('settings.connected-apps.destroy', $this->client->getKey()))
        ->assertRedirect(route('settings.connected-apps.index'))
        ->assertSessionHas('status');

    expect($token->fresh()->revoked)->toBeTrue()
        ->and($refreshToken->fresh()->revoked)->toBeTrue();
});

it('does not revoke apps authorized by other users', function () {
    $otherToken = issueMcpToken(User::factory()->create(), $this->client);

    actingAs($this->user)
        ->delete(route('settings.connected-apps.destroy', $this->client->getKey()))
        ->assertNotFound();

    expect($otherToken->fresh()->revoked)->toBeFalse();
});

it('is not available when MCP is disabled', function () {
    config(['pricore.mcp.enabled' => false]);
    $token = issueMcpToken($this->user, $this->client);

    actingAs($this->user)->get(route('settings.connected-apps.index'))->assertNotFound();
    actingAs($this->user)->delete(route('settings.connected-apps.destroy', $this->client->getKey()))->assertNotFound();

    expect($token->fresh()->revoked)->toBeFalse();
});
