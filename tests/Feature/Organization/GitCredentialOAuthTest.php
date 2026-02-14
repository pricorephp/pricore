<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
use App\Models\User;

uses()->group('organizations', 'git-credentials', 'oauth');

beforeEach(function () {
    $this->user = User::factory()->withGitHub()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

test('can store GitHub credentials via OAuth source', function () {
    $response = $this->actingAs($this->user)
        ->post("/organizations/{$this->organization->slug}/settings/git-credentials", [
            'provider' => 'github',
            'source' => 'oauth',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Git credentials added successfully.');

    $credential = OrganizationGitCredential::where('organization_uuid', $this->organization->uuid)
        ->where('provider', 'github')
        ->first();

    expect($credential)->not->toBeNull()
        ->and($credential->source_user_uuid)->toBe($this->user->uuid)
        ->and($credential->credentials['token'])->toBe($this->user->github_token);
});

test('rejects OAuth source when user has no GitHub connected', function () {
    $userWithoutGitHub = User::factory()->create();
    $this->organization->members()->attach($userWithoutGitHub->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);

    $response = $this->actingAs($userWithoutGitHub)
        ->post("/organizations/{$this->organization->slug}/settings/git-credentials", [
            'provider' => 'github',
            'source' => 'oauth',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect(OrganizationGitCredential::where('organization_uuid', $this->organization->uuid)->count())->toBe(0);
});

test('source_user_uuid is tracked correctly', function () {
    $this->actingAs($this->user)
        ->post("/organizations/{$this->organization->slug}/settings/git-credentials", [
            'provider' => 'github',
            'source' => 'oauth',
        ]);

    $credential = OrganizationGitCredential::where('organization_uuid', $this->organization->uuid)->first();

    expect($credential->source_user_uuid)->toBe($this->user->uuid);
});

test('manual credentials do not set source_user_uuid', function () {
    $this->actingAs($this->user)
        ->post("/organizations/{$this->organization->slug}/settings/git-credentials", [
            'provider' => 'github',
            'credentials' => ['token' => 'ghp_manual_token'],
        ]);

    $credential = OrganizationGitCredential::where('organization_uuid', $this->organization->uuid)->first();

    expect($credential)->not->toBeNull()
        ->and($credential->source_user_uuid)->toBeNull()
        ->and($credential->credentials['token'])->toBe('ghp_manual_token');
});

test('git credentials index passes hasGitHubConnected', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/settings/git-credentials");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/git-credentials')
        ->where('hasGitHubConnected', true)
    );
});

test('git credentials index passes false when no GitHub connected', function () {
    $userWithoutGitHub = User::factory()->create();
    $this->organization->members()->attach($userWithoutGitHub->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $response = $this->actingAs($userWithoutGitHub)
        ->get("/organizations/{$this->organization->slug}/settings/git-credentials");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/git-credentials')
        ->where('hasGitHubConnected', false)
    );
});
