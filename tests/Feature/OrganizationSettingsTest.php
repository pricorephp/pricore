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

it('owner can update organization slug', function () {
    $this->organization->update(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => $this->organization->name,
        'slug' => 'new-slug-name',
    ]);

    $response->assertRedirect();
    expect($this->organization->fresh()->slug)->toBe('new-slug-name');
});

it('admin cannot update organization slug', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $originalSlug = $this->organization->slug;

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => $this->organization->name,
        'slug' => 'new-slug-name',
    ]);

    $response->assertRedirect();
    expect($this->organization->fresh()->slug)->toBe($originalSlug);
});

it('validates slug format', function () {
    $this->organization->update(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => $this->organization->name,
        'slug' => 'Invalid Slug With Spaces',
    ]);

    $response->assertInvalid(['slug']);
});

it('validates slug uniqueness', function () {
    $otherOrganization = Organization::factory()->create(['slug' => 'existing-slug']);

    $this->organization->update(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => $this->organization->name,
        'slug' => 'existing-slug',
    ]);

    $response->assertInvalid(['slug']);
});

it('allows owner to keep same slug', function () {
    $this->organization->update(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $originalSlug = $this->organization->slug;

    $response = $this->actingAs($this->user)->patch("/organizations/{$this->organization->slug}/settings/general", [
        'name' => 'Updated Name',
        'slug' => $originalSlug,
    ]);

    $response->assertRedirect();
    expect($this->organization->fresh()->slug)->toBe($originalSlug);
});

it('passes isOwner prop to frontend for owner', function () {
    $this->organization->update(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('organization')
        ->where('isOwner', true)
    );
});

it('passes isOwner prop to frontend for admin', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/general");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('organization')
        ->where('isOwner', false)
    );
});
