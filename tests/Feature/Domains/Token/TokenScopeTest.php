<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->admin->uuid]);
    $this->organization->members()->attach($this->admin->uuid, ['uuid' => (string) Str::uuid(), 'role' => OrganizationRole::Owner->value]);
});

it('creates Composer-only organization tokens by default', function () {
    actingAs($this->admin)
        ->post(route('organizations.settings.tokens.store', $this->organization->slug), ['name' => 'Deploy'])
        ->assertSuccessful();

    $accessToken = AccessToken::query()->where('name', 'Deploy')->firstOrFail();

    expect($accessToken->scopes)->toBe([TokenScope::Composer->value])
        ->and($accessToken->hasScope(TokenScope::WritePackages))->toBeFalse();
});

it('creates organization tokens that can publish when asked', function () {
    actingAs($this->admin)
        ->post(route('organizations.settings.tokens.store', $this->organization->slug), ['name' => 'CI', 'scopes' => ['composer', 'write:packages']])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('tokens.0.canPublish', true));

    expect(AccessToken::query()->where('name', 'CI')->firstOrFail()->hasScope(TokenScope::WritePackages))->toBeTrue();
});

it('creates personal tokens that can publish when asked', function () {
    actingAs($this->admin)
        ->post('/settings/tokens', ['name' => 'Laptop', 'scopes' => ['composer', 'write:packages']])
        ->assertSuccessful();

    expect(AccessToken::query()->where('name', 'Laptop')->firstOrFail())
        ->user_uuid->toBe($this->admin->uuid)
        ->hasScope(TokenScope::WritePackages)->toBeTrue();
});

it('treats tokens without stored scopes as Composer-only', function () {
    $accessToken = AccessToken::factory()->forOrganization($this->organization)->create(['scopes' => null]);

    expect($accessToken->hasScope(TokenScope::Composer))->toBeTrue()
        ->and($accessToken->hasScope(TokenScope::WritePackages))->toBeFalse()
        ->and($accessToken->hasScope(TokenScope::ReadRepositories))->toBeFalse();
});

it('converts read and write scopes when migrating', function () {
    $migration = require database_path('migrations/2026_11_01_000001_convert_access_token_scopes.php');

    $legacy = AccessToken::factory()->forOrganization($this->organization)->create(['scopes' => null]);
    $readOnly = AccessToken::factory()->forOrganization($this->organization)->create();
    $publishing = AccessToken::factory()->forOrganization($this->organization)->create();

    DB::table('access_tokens')->where('uuid', $readOnly->uuid)->update(['scopes' => json_encode(['read'])]);
    DB::table('access_tokens')->where('uuid', $publishing->uuid)->update(['scopes' => json_encode(['read', 'write'])]);

    $migration->up();

    expect($legacy->refresh()->scopes)->toBe(['composer'])
        ->and($readOnly->refresh()->scopes)->toBe(['composer'])
        ->and($publishing->refresh()->scopes)->toBe(['composer', 'write:packages']);

    $migration->down();

    expect($legacy->refresh()->scopes)->toBe(['read'])
        ->and($publishing->refresh()->scopes)->toBe(['read', 'write']);
});
