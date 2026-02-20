<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses()->group('organizations', 'members');

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->admin->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);
});

it('admin can view members page', function () {
    $response = $this->actingAs($this->admin)->get("/organizations/{$this->organization->slug}/settings/members");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/members')
        ->has('members')
        ->has('invitations')
        ->has('roleOptions')
    );
});

it('member cannot view members page', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($member)->get("/organizations/{$this->organization->slug}/settings/members");

    $response->assertForbidden();
});

it('admin can invite user by email', function () {
    Notification::fake();

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => 'newuser@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    expect(OrganizationInvitation::where('email', 'newuser@example.com')
        ->where('organization_uuid', $this->organization->uuid)
        ->exists()
    )->toBeTrue();

    Notification::assertSentOnDemand(
        \App\Notifications\OrganizationInvitationNotification::class,
    );
});

it('admin can invite existing user by email', function () {
    Notification::fake();
    $existingUser = User::factory()->create();

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => $existingUser->email,
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    expect(OrganizationInvitation::where('email', $existingUser->email)
        ->where('organization_uuid', $this->organization->uuid)
        ->exists()
    )->toBeTrue();

    // User should NOT be directly added as a member
    expect($this->organization->members()->where('user_uuid', $existingUser->uuid)->exists())->toBeFalse();
});

it('cannot invite user that is already a member', function () {
    $existingMember = User::factory()->create();
    $this->organization->members()->attach($existingMember->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => $existingMember->email,
        'role' => OrganizationRole::Admin->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

it('cannot send duplicate pending invitation', function () {
    Notification::fake();

    OrganizationInvitation::factory()->forOrganization($this->organization)->create([
        'email' => 'duplicate@example.com',
    ]);

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => 'duplicate@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

it('validates role is required when adding member', function () {
    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => 'test@example.com',
        'role' => '',
    ]);

    $response->assertInvalid(['role']);
});

it('admin can update member role', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $organizationUser = OrganizationUser::where('user_uuid', $member->uuid)
        ->where('organization_uuid', $this->organization->uuid)
        ->first();

    $response = $this->actingAs($this->admin)->patch("/organizations/{$this->organization->slug}/settings/members/{$organizationUser->uuid}", [
        'role' => OrganizationRole::Admin->value,
    ]);

    $response->assertRedirect();
    expect($organizationUser->fresh()->role)->toBe(OrganizationRole::Admin);
});

it('cannot change owner role', function () {
    $owner = User::factory()->create();
    $this->organization->update(['owner_uuid' => $owner->uuid]);
    $this->organization->members()->attach($owner->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $organizationUser = OrganizationUser::where('user_uuid', $owner->uuid)
        ->where('organization_uuid', $this->organization->uuid)
        ->first();

    $response = $this->actingAs($this->admin)->patch("/organizations/{$this->organization->slug}/settings/members/{$organizationUser->uuid}", [
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($organizationUser->fresh()->role)->toBe(OrganizationRole::Owner);
});

it('admin can remove member', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $organizationUser = OrganizationUser::where('user_uuid', $member->uuid)
        ->where('organization_uuid', $this->organization->uuid)
        ->first();

    $response = $this->actingAs($this->admin)->delete("/organizations/{$this->organization->slug}/settings/members/{$organizationUser->uuid}");

    $response->assertRedirect();
    expect($this->organization->members()->where('user_uuid', $member->uuid)->exists())->toBeFalse();
});

it('cannot remove organization owner', function () {
    $owner = User::factory()->create();
    $this->organization->update(['owner_uuid' => $owner->uuid]);
    $this->organization->members()->attach($owner->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $organizationUser = OrganizationUser::where('user_uuid', $owner->uuid)
        ->where('organization_uuid', $this->organization->uuid)
        ->first();

    $response = $this->actingAs($this->admin)->delete("/organizations/{$this->organization->slug}/settings/members/{$organizationUser->uuid}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($this->organization->members()->where('user_uuid', $owner->uuid)->exists())->toBeTrue();
});

it('member cannot add other members', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($member)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => 'new@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertForbidden();
});
