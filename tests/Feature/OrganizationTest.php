<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;

uses()->group('organizations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => 'member',
    ]);
});

it('shows organization overview page', function () {
    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/show')
        ->has('organization')
        ->has('stats')
    );
});

it('shows packages page', function () {
    Package::factory()->count(3)->create([
        'organization_uuid' => $this->organization->uuid,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/packages");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/packages')
        ->has('organization')
        ->has('packages', 3)
    );
});

it('shows repositories page', function () {
    Repository::factory()->count(2)->create([
        'organization_uuid' => $this->organization->uuid,
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/repositories");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/repositories')
        ->has('organization')
        ->has('repositories', 2)
    );
});

it('requires authentication to view organization pages', function () {
    $response = $this->get("/organizations/{$this->organization->slug}");

    $response->assertRedirect(route('login'));
});

it('shares organizations in inertia props', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('auth.organizations')
    );
});

it('creates organization with valid name', function () {
    $response = $this->actingAs($this->user)->post('/organizations', [
        'name' => 'Test Company',
    ]);

    $response->assertRedirect();
    expect(Organization::where('name', 'Test Company')->exists())->toBeTrue();
});

it('auto-generates slug from name', function () {
    $this->actingAs($this->user)->post('/organizations', [
        'name' => 'My Test Company',
    ]);

    expect(Organization::where('slug', 'my-test-company')->exists())->toBeTrue();
});

it('handles duplicate slugs by appending numbers', function () {
    Organization::factory()->create(['slug' => 'acme']);

    $this->actingAs($this->user)->post('/organizations', [
        'name' => 'ACME',
    ]);

    expect(Organization::where('slug', 'acme-2')->exists())->toBeTrue();
});

it('sets creator as owner', function () {
    $this->actingAs($this->user)->post('/organizations', [
        'name' => 'Test Org',
    ]);

    $org = Organization::where('name', 'Test Org')->first();
    expect($org->owner_uuid)->toBe($this->user->uuid);
});

it('adds creator to organization members', function () {
    $this->actingAs($this->user)->post('/organizations', [
        'name' => 'Test Org',
    ]);

    $org = Organization::where('name', 'Test Org')->first();
    expect($org->members()->where('user_uuid', $this->user->uuid)->exists())->toBeTrue();
    expect($org->members()->where('user_uuid', $this->user->uuid)->first()->pivot->role)->toBe(OrganizationRole::Owner);
});

it('requires authentication to create organization', function () {
    $response = $this->post('/organizations', [
        'name' => 'Test Org',
    ]);

    $response->assertRedirect(route('login'));
});

it('validates required name field', function () {
    $response = $this->actingAs($this->user)->post('/organizations', [
        'name' => '',
    ]);

    $response->assertInvalid(['name']);
});

it('redirects to organization overview after creation', function () {
    $response = $this->actingAs($this->user)->post('/organizations', [
        'name' => 'New Org',
    ]);

    $org = Organization::where('name', 'New Org')->first();
    $response->assertRedirect(route('organizations.show', $org->slug));
});
