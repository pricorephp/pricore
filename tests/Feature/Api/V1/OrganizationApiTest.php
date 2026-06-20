<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('creates an organization with a personal access token', function () {
    $user = User::factory()->create();
    $token = personalAccessToken($user, [TokenScope::WriteOrganizations]);

    $response = $this->withToken($token)
        ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
        ->assertCreated()
        ->assertJsonPath('name', 'Acme Inc');

    assertDatabaseHas('organizations', [
        'uuid' => $response->json('uuid'),
        'owner_uuid' => $user->uuid,
    ]);
});

it('does not allow organization-scoped tokens to create organizations', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($organization, $owner, OrganizationRole::Owner);

    $token = organizationAccessToken($organization, [TokenScope::WriteOrganizations]);

    $this->withToken($token)
        ->postJson('/api/v1/organizations', ['name' => 'Nope'])
        ->assertForbidden();
});

it('shows an organization to a member', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    joinOrganization($organization, $user, OrganizationRole::Owner);

    $token = personalAccessToken($user, [TokenScope::ReadOrganizations]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$organization->slug}")
        ->assertOk()
        ->assertJsonPath('slug', $organization->slug);
});

it('updates an organization name and slug as the owner', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    joinOrganization($organization, $user, OrganizationRole::Owner);

    $token = personalAccessToken($user, [TokenScope::WriteOrganizations]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$organization->slug}", [
            'name' => 'Renamed Org',
            'slug' => 'renamed-org',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed Org')
        ->assertJsonPath('slug', 'renamed-org');
});

it('forbids a plain member from updating the organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($organization, $owner, OrganizationRole::Owner);

    $member = User::factory()->create();
    joinOrganization($organization, $member, OrganizationRole::Member);

    $token = personalAccessToken($member, [TokenScope::WriteOrganizations]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$organization->slug}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('deletes an organization as the owner but forbids an admin', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($organization, $owner, OrganizationRole::Owner);

    $admin = User::factory()->create();
    joinOrganization($organization, $admin, OrganizationRole::Admin);

    // Admin has the scope but not ownership — policy denies.
    $adminToken = personalAccessToken($admin, [TokenScope::DeleteOrganizations]);
    $this->withToken($adminToken)
        ->deleteJson("/api/v1/organizations/{$organization->slug}")
        ->assertForbidden();

    $ownerToken = personalAccessToken($owner, [TokenScope::DeleteOrganizations]);
    $this->withToken($ownerToken)
        ->deleteJson("/api/v1/organizations/{$organization->slug}")
        ->assertNoContent();

    assertDatabaseMissing('organizations', ['uuid' => $organization->uuid, 'deleted_at' => null]);
});
