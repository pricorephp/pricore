<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses()->group('settings', 'organizations');

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can view organizations settings page', function () {
    $organization = Organization::factory()->create();
    $organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.organizations'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/organizations')
            ->has('organizations', 1)
            ->where('organizations.0.organization.name', $organization->name)
            ->where('organizations.0.role', OrganizationRole::Member->value)
            ->where('organizations.0.isOwner', false)
        );
});

it('shows all organizations user belongs to', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $org3 = Organization::factory()->create();

    $org1->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);
    $org2->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);
    // User doesn't belong to org3

    $this->actingAs($this->user)
        ->get(route('settings.organizations'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/organizations')
            ->has('organizations', 2)
        );
});

it('identifies organization owner correctly', function () {
    $organization = Organization::factory()->create([
        'owner_uuid' => $this->user->uuid,
    ]);
    $organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.organizations'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/organizations')
            ->where('organizations.0.isOwner', true)
        );
});

it('member can leave organization', function () {
    $organization = Organization::factory()->create();
    $organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('settings.organizations.leave', $organization->slug));

    $response->assertRedirect(route('settings.organizations'));
    $response->assertSessionHas('success');
    expect($organization->members()->where('user_uuid', $this->user->uuid)->exists())->toBeFalse();
});

it('admin can leave organization', function () {
    $organization = Organization::factory()->create();
    $organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('settings.organizations.leave', $organization->slug));

    $response->assertRedirect(route('settings.organizations'));
    $response->assertSessionHas('success');
    expect($organization->members()->where('user_uuid', $this->user->uuid)->exists())->toBeFalse();
});

it('owner cannot leave organization', function () {
    $organization = Organization::factory()->create([
        'owner_uuid' => $this->user->uuid,
    ]);
    $organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('settings.organizations.leave', $organization->slug));

    $response->assertRedirect(route('settings.organizations'));
    $response->assertSessionHas('error', 'You cannot leave an organization you own.');
    expect($organization->members()->where('user_uuid', $this->user->uuid)->exists())->toBeTrue();
});

it('cannot leave organization user is not a member of', function () {
    $organization = Organization::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete(route('settings.organizations.leave', $organization->slug));

    $response->assertRedirect(route('settings.organizations'));
    $response->assertSessionHas('error', 'You are not a member of this organization.');
});

it('requires authentication to view organizations settings', function () {
    $this->get(route('settings.organizations'))
        ->assertRedirect(route('login'));
});

it('requires authentication to leave organization', function () {
    $organization = Organization::factory()->create();

    $this->delete(route('settings.organizations.leave', $organization->slug))
        ->assertRedirect(route('login'));
});
