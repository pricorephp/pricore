<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->owner->uuid]);
    joinOrganization($this->organization, $this->owner, OrganizationRole::Owner);
});

it('lists members with a pagination envelope', function () {
    $token = personalAccessToken($this->owner, [TokenScope::ReadMembers]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/members")
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonPath('data.0.email', $this->owner->email);
});

it('invites a member', function () {
    Notification::fake();

    $token = personalAccessToken($this->owner, [TokenScope::WriteMembers]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/members", [
            'email' => 'new@example.com',
            'role' => 'member',
        ])
        ->assertCreated()
        ->assertJsonPath('email', 'new@example.com');

    assertDatabaseHas('organization_invitations', [
        'organization_uuid' => $this->organization->uuid,
        'email' => 'new@example.com',
    ]);
});

it('forbids a plain member from inviting', function () {
    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);

    $token = personalAccessToken($member, [TokenScope::WriteMembers]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/members", [
            'email' => 'new@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();
});

it('changes the role of a member', function () {
    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);
    $membership = OrganizationUser::query()->where('user_uuid', $member->uuid)->firstOrFail();

    $token = personalAccessToken($this->owner, [TokenScope::WriteMembers]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/members/{$membership->uuid}", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('role', 'admin');

    expect($membership->refresh()->role)->toBe(OrganizationRole::Admin);
});

it('removes a member', function () {
    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);
    $membership = OrganizationUser::query()->where('user_uuid', $member->uuid)->firstOrFail();

    $token = personalAccessToken($this->owner, [TokenScope::WriteMembers]);

    $this->withToken($token)
        ->deleteJson("/api/v1/organizations/{$this->organization->slug}/members/{$membership->uuid}")
        ->assertNoContent();

    expect(OrganizationUser::query()->whereKey($membership->uuid)->exists())->toBeFalse();
});

it('does not reach members of another organization', function () {
    $otherOrganization = Organization::factory()->create();
    $outsider = User::factory()->create();
    joinOrganization($otherOrganization, $outsider, OrganizationRole::Member);
    $membership = OrganizationUser::query()->where('user_uuid', $outsider->uuid)->firstOrFail();

    $token = personalAccessToken($this->owner, [TokenScope::WriteMembers]);

    $this->withToken($token)
        ->deleteJson("/api/v1/organizations/{$this->organization->slug}/members/{$membership->uuid}")
        ->assertNotFound();

    expect(OrganizationUser::query()->whereKey($membership->uuid)->exists())->toBeTrue();
});
