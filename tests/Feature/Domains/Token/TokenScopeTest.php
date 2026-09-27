<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->admin->uuid]);
    $this->organization->members()->attach($this->admin->uuid, ['uuid' => (string) Str::uuid(), 'role' => OrganizationRole::Owner->value]);
});

it('creates read-only organization tokens by default', function () {
    actingAs($this->admin)
        ->post(route('organizations.settings.tokens.store', $this->organization->slug), ['name' => 'Deploy'])
        ->assertSuccessful();

    $accessToken = AccessToken::query()->where('name', 'Deploy')->firstOrFail();

    expect($accessToken->scopes)->toBe([TokenScope::Read->value])
        ->and($accessToken->hasScope(TokenScope::Write))->toBeFalse();
});

it('creates organization tokens that can publish when asked', function () {
    actingAs($this->admin)
        ->post(route('organizations.settings.tokens.store', $this->organization->slug), ['name' => 'CI', 'can_publish' => '1'])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('tokens.0.canPublish', true));

    expect(AccessToken::query()->where('name', 'CI')->firstOrFail()->hasScope(TokenScope::Write))->toBeTrue();
});

it('creates personal tokens that can publish when asked', function () {
    actingAs($this->admin)
        ->post('/settings/tokens', ['name' => 'Laptop', 'can_publish' => '1'])
        ->assertSuccessful();

    expect(AccessToken::query()->where('name', 'Laptop')->firstOrFail())
        ->user_uuid->toBe($this->admin->uuid)
        ->hasScope(TokenScope::Write)->toBeTrue();
});

it('treats tokens without stored scopes as read-only', function () {
    $accessToken = AccessToken::factory()->forOrganization($this->organization)->create(['scopes' => null]);

    expect($accessToken->hasScope(TokenScope::Read))->toBeTrue()
        ->and($accessToken->hasScope(TokenScope::Write))->toBeFalse();
});
