<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;

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

it('admin can add member by email', function () {
    $newUser = User::factory()->create();

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => $newUser->email,
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertRedirect();
    expect($this->organization->members()->where('user_uuid', $newUser->uuid)->exists())->toBeTrue();
});

it('cannot add user that does not exist', function () {
    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => 'nonexistent@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertInvalid(['email']);
});

it('cannot add user that is already a member', function () {
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

it('validates role is required when adding member', function () {
    $newUser = User::factory()->create();

    $response = $this->actingAs($this->admin)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => $newUser->email,
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
    expect($organizationUser->fresh()->role)->toBe(OrganizationRole::Admin->value);
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
    expect($organizationUser->fresh()->role)->toBe(OrganizationRole::Owner->value);
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

    $newUser = User::factory()->create();

    $response = $this->actingAs($member)->post("/organizations/{$this->organization->slug}/settings/members", [
        'email' => $newUser->email,
        'role' => OrganizationRole::Member->value,
    ]);

    $response->assertForbidden();
});
