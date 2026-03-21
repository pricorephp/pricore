<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationSshKey;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('organizations', 'settings', 'ssh-keys');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
});

it('admin can access ssh keys page', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/ssh-keys");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/ssh-keys')
        ->has('organization')
        ->has('sshKeys')
    );
});

it('shows empty state when no ssh keys', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/ssh-keys");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('sshKeys', 0)
    );
});

it('shows existing ssh keys', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    OrganizationSshKey::factory()
        ->for($this->organization, 'organization')
        ->count(2)
        ->create();

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/ssh-keys");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('sshKeys', 2)
    );
});

it('member cannot access ssh keys page', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/ssh-keys");

    $response->assertForbidden();
});

it('non-member cannot access ssh keys page', function () {
    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/ssh-keys");

    $response->assertForbidden();
});

it('admin can generate an ssh key', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/ssh-keys", [
        'name' => 'My Deploy Key',
    ]);

    $response->assertRedirect();

    $sshKey = $this->organization->sshKeys()->first();
    expect($sshKey)->not->toBeNull();
    expect($sshKey->name)->toBe('My Deploy Key');
    expect($sshKey->public_key)->toStartWith('ssh-ed25519 ');
    expect($sshKey->fingerprint)->toStartWith('SHA256:');
    expect($sshKey->private_key)->toContain('OPENSSH PRIVATE KEY');
});

it('validates name is required when generating key', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/ssh-keys", [
        'name' => '',
    ]);

    $response->assertInvalid(['name']);
});

it('member cannot generate an ssh key', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/ssh-keys", [
        'name' => 'My Key',
    ]);

    $response->assertForbidden();
});

it('admin can delete an ssh key', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $sshKey = OrganizationSshKey::factory()
        ->for($this->organization, 'organization')
        ->create();

    $response = $this->actingAs($this->user)->delete("/organizations/{$this->organization->slug}/settings/ssh-keys/{$sshKey->uuid}");

    $response->assertRedirect();
    expect(OrganizationSshKey::find($sshKey->uuid))->toBeNull();
});

it('cannot delete ssh key from another organization', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $otherOrganization = Organization::factory()->create();
    $sshKey = OrganizationSshKey::factory()
        ->for($otherOrganization, 'organization')
        ->create();

    $response = $this->actingAs($this->user)->delete("/organizations/{$this->organization->slug}/settings/ssh-keys/{$sshKey->uuid}");

    $response->assertNotFound();
    expect(OrganizationSshKey::find($sshKey->uuid))->not->toBeNull();
});

it('member cannot delete an ssh key', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $sshKey = OrganizationSshKey::factory()
        ->for($this->organization, 'organization')
        ->create();

    $response = $this->actingAs($this->user)->delete("/organizations/{$this->organization->slug}/settings/ssh-keys/{$sshKey->uuid}");

    $response->assertForbidden();
});

it('can generate multiple ssh keys per organization', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/ssh-keys", [
        'name' => 'Key One',
    ]);

    $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/ssh-keys", [
        'name' => 'Key Two',
    ]);

    expect($this->organization->sshKeys()->count())->toBe(2);
});
