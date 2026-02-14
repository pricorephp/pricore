<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

uses()->group('organizations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->ownedBy($this->user)->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

it('owner can delete organization', function () {
    $response = $this->actingAs($this->user)->delete("/organizations/{$this->organization->slug}");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('status', 'Organization deleted successfully.');
    expect($this->organization->fresh()->trashed())->toBeTrue();
});

it('admin cannot delete organization', function () {
    $admin = User::factory()->create();
    $this->organization->members()->attach($admin->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($admin)->delete("/organizations/{$this->organization->slug}");

    $response->assertForbidden();
    expect($this->organization->fresh()->trashed())->toBeFalse();
});

it('member cannot delete organization', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($member)->delete("/organizations/{$this->organization->slug}");

    $response->assertForbidden();
    expect($this->organization->fresh()->trashed())->toBeFalse();
});

it('non-member cannot delete organization', function () {
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->delete("/organizations/{$this->organization->slug}");

    $response->assertForbidden();
    expect($this->organization->fresh()->trashed())->toBeFalse();
});

it('soft deleted organization is no longer accessible', function () {
    $this->actingAs($this->user)->delete("/organizations/{$this->organization->slug}");

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

    $response->assertNotFound();
});
