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

test('git credentials index passes githubConnectUrl', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}/settings/git-credentials");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/git-credentials')
        ->where('githubConnectUrl', route('auth.github.connect', $this->organization))
    );
});

test('store requires credentials token for github provider', function () {
    $response = $this->actingAs($this->user)
        ->post("/organizations/{$this->organization->slug}/settings/git-credentials", [
            'provider' => 'github',
        ]);

    $response->assertSessionHasErrors('credentials.token');
});
