<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;

uses()->group('security', 'composer');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'acme',
        'owner_uuid' => $this->user->uuid,
    ]);

    $this->plainToken = 'test-token-'.uniqid();

    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();
});

it('locks out an ip that keeps presenting invalid tokens', function () {
    config()->set('pricore.rate_limits.composer_auth', 5);

    foreach (range(1, 5) as $attempt) {
        $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
            ->getJson("/{$this->organization->slug}/packages.json")
            ->assertUnauthorized();
    }

    $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
        ->getJson("/{$this->organization->slug}/packages.json")
        ->assertStatus(429);
});

it('does not count successful requests towards the auth lockout', function () {
    config()->set('pricore.rate_limits.composer_auth', 3);

    foreach (range(1, 10) as $attempt) {
        $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
            ->getJson("/{$this->organization->slug}/packages.json")
            ->assertOk();
    }
});

it('applies a generous per-token limit to valid composer traffic', function () {
    $limit = (int) config('pricore.rate_limits.composer');

    expect($limit)->toBeGreaterThanOrEqual(300);

    $response = $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->getJson("/{$this->organization->slug}/packages.json");

    $response->assertOk()->assertHeader('X-RateLimit-Limit', (string) $limit);
});

it('only records last_used_at once per minute', function () {
    $token = AccessToken::query()->firstOrFail();

    $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->getJson("/{$this->organization->slug}/packages.json")
        ->assertOk();

    $firstUsedAt = $token->fresh()->last_used_at;

    expect($firstUsedAt)->not->toBeNull();

    $this->travelTo(now()->addSeconds(10));

    $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->getJson("/{$this->organization->slug}/packages.json")
        ->assertOk();

    expect($token->fresh()->last_used_at->timestamp)->toBe($firstUsedAt->timestamp);

    $this->travelTo(now()->addMinutes(2));

    $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->getJson("/{$this->organization->slug}/packages.json")
        ->assertOk();

    expect($token->fresh()->last_used_at->timestamp)->toBeGreaterThan($firstUsedAt->timestamp);
});
