<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\User;

it('lets a personal access token act across all organizations the user belongs to', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $orgB = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    joinOrganization($orgA, $user, OrganizationRole::Owner);
    joinOrganization($orgB, $user, OrganizationRole::Owner);

    $token = personalAccessToken($user, [TokenScope::ReadRepositories]);

    $this->withToken($token)->getJson("/api/v1/organizations/{$orgA->slug}/repositories")->assertOk();
    $this->withToken($token)->getJson("/api/v1/organizations/{$orgB->slug}/repositories")->assertOk();
});

it('forbids a personal access token from organizations the user does not belong to', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    joinOrganization($orgA, $user, OrganizationRole::Owner);

    $otherOrg = Organization::factory()->create();

    $token = personalAccessToken($user, [TokenScope::ReadRepositories]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$otherOrg->slug}/repositories")
        ->assertForbidden();
});

it('isolates an organization-scoped token to its own organization', function () {
    $owner = User::factory()->create();
    $orgA = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $orgB = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($orgA, $owner, OrganizationRole::Owner);
    joinOrganization($orgB, $owner, OrganizationRole::Owner);

    $token = organizationAccessToken($orgA, [TokenScope::ReadRepositories]);

    $this->withToken($token)->getJson("/api/v1/organizations/{$orgA->slug}/repositories")->assertOk();

    // Even though the owner is a member of org B, the org-A token must not reach it.
    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$orgB->slug}/repositories")
        ->assertForbidden();
});

it('forbids organization-scoped tokens from personal endpoints', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($organization, $owner, OrganizationRole::Owner);

    $token = organizationAccessToken($organization);

    $this->withToken($token)->getJson('/api/v1/user')->assertForbidden();
});

it('limits the organization listing of an organization token to its own organization', function () {
    $owner = User::factory()->create();
    $orgA = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $orgB = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    joinOrganization($orgA, $owner, OrganizationRole::Owner);
    joinOrganization($orgB, $owner, OrganizationRole::Owner);

    $token = organizationAccessToken($orgA, [TokenScope::ReadOrganizations]);

    $response = $this->withToken($token)->getJson('/api/v1/organizations')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.uuid'))->toBe($orgA->uuid);
});
