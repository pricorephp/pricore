<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Organization;
use App\Models\OrganizationSshKey;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

uses()->group('security', 'ssh-keys');

/**
 * SSH keys are deploy credentials for an organization's private repositories. A user
 * who belongs to two organizations can read one organization's key UUIDs from its
 * settings page, so the UUID alone must never be enough to use the key elsewhere.
 */
it('cannot attach another organizations ssh key to a repository', function () {
    $user = User::factory()->create();

    $victim = Organization::factory()->create();
    $victim->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $attacker = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $attacker->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $victimKey = OrganizationSshKey::factory()->create(['organization_uuid' => $victim->uuid]);

    actingAs($user)
        ->post(route('organizations.repositories.store', $attacker->slug), [
            'provider' => GitProvider::Git->value,
            'repo_identifier' => 'git@git.example.com:acme/widgets.git',
            'ssh_key_uuid' => $victimKey->uuid,
        ])
        ->assertSessionHasErrors('ssh_key_uuid');

    assertDatabaseCount('repositories', 0);
});

it('accepts an ssh key belonging to the same organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $key = OrganizationSshKey::factory()->create(['organization_uuid' => $organization->uuid]);

    actingAs($user)
        ->post(route('organizations.repositories.store', $organization->slug), [
            'provider' => GitProvider::Git->value,
            'repo_identifier' => 'git@git.example.com:acme/widgets.git',
            'ssh_key_uuid' => $key->uuid,
        ])
        ->assertSessionHasNoErrors();

    assertDatabaseCount('repositories', 1);
});

it('does not load an ssh key from another organization when building a provider', function () {
    $victim = Organization::factory()->create();
    $victimKey = OrganizationSshKey::factory()->create(['organization_uuid' => $victim->uuid]);

    $attacker = Organization::factory()->create();

    // Bypass validation entirely — the factory writes the foreign UUID straight to the row.
    $repository = Repository::factory()->create([
        'organization_uuid' => $attacker->uuid,
        'provider' => GitProvider::Git,
        'repo_identifier' => 'git@git.example.com:acme/widgets.git',
        'ssh_key_uuid' => $victimKey->uuid,
    ]);

    $provider = GitProviderFactory::make($repository);

    $credentials = (new ReflectionClass($provider))
        ->getProperty('credentials')
        ->getValue($provider);

    expect($credentials)->not->toHaveKey('ssh_key');
});
