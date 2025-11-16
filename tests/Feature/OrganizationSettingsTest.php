<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

uses()->group('organizations', 'settings');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
});

it('owner can access settings general page', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/general')
        ->has('organization')
    );
});

it('admin can access settings general page', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertSuccessful();
});

it('regular member cannot access settings', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertForbidden();
});

it('non-member cannot access settings', function () {
    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertForbidden();
});

it('owner can update organization name', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => 'Updated Organization Name',
    ]);

    $response->assertRedirect();
    expect($this->organization->fresh()->name)->toBe('Updated Organization Name');
});

it('admin can update organization name', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => 'Updated Name',
    ]);

    $response->assertRedirect();
    expect($this->organization->fresh()->name)->toBe('Updated Name');
});

it('member cannot update organization name', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => 'New Name',
    ]);

    $response->assertForbidden();
});

it('validates organization name is required', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => '',
    ]);

    $response->assertInvalid(['name']);
});

it('redirects settings index to general page', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings");

    $response->assertRedirect("/organizations/{$this->organization->slug}/settings/general");
});
