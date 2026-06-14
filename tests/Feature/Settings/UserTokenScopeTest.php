<?php

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('creates a personal access token with the selected scopes', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('settings.tokens.store'), [
        'name' => 'Scoped token',
        'scopes' => ['composer', 'read:repositories'],
    ]);

    $token = AccessToken::query()->where('user_uuid', $user->uuid)->firstOrFail();

    expect($token->scopes)->toEqual(['composer', 'read:repositories']);
});

it('defaults to the composer scope when none are selected', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('settings.tokens.store'), [
        'name' => 'Default token',
    ]);

    $token = AccessToken::query()->where('user_uuid', $user->uuid)->firstOrFail();

    expect($token->scopes)->toEqual(['composer']);
});

it('updates a personal token name and scopes', function () {
    $user = User::factory()->create();
    $token = AccessToken::factory()
        ->forUser($user)
        ->withScopes([TokenScope::Composer])
        ->create();

    actingAs($user)->patch(route('settings.tokens.update', $token->uuid), [
        'name' => 'Renamed token',
        'scopes' => ['composer', 'read:repositories'],
    ]);

    $token->refresh();

    expect($token->name)->toBe('Renamed token');
    expect($token->scopes)->toEqual(['composer', 'read:repositories']);
});
