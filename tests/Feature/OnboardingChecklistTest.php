<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('onboarding');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => 'member',
    ]);
});

it('passes onboarding prop to organization show page', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/show')
        ->has('onboarding')
        ->where('onboarding.isDismissed', false)
    );
});

it('detects when organization has repositories', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasRepository', false)
    );

    Repository::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasRepository', true)
    );
});

it('detects when user has a personal token', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasPersonalToken', false)
    );

    AccessToken::factory()->forUser($this->user)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasPersonalToken', true)
    );
});

it('detects when organization has a token', function () {
    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasOrgToken', false)
    );

    AccessToken::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasOrgToken', true)
    );
});

it('allows dismissing the onboarding checklist', function () {
    $response = $this->actingAs($this->user)
        ->post("/organizations/{$this->organization->slug}/dismiss-onboarding");

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->hasOnboardingDismissed($this->organization->uuid))->toBeTrue();
});

it('returns isDismissed true after dismissing', function () {
    $this->user->dismissOnboarding($this->organization->uuid);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$this->organization->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.isDismissed', true)
    );
});

it('scopes dismissal per organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherOrg->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => 'member',
    ]);

    $this->user->dismissOnboarding($this->organization->uuid);

    $response = $this->actingAs($this->user)
        ->get("/organizations/{$otherOrg->slug}");

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.isDismissed', false)
    );
});

it('requires authentication to dismiss onboarding', function () {
    $response = $this->post("/organizations/{$this->organization->slug}/dismiss-onboarding");

    $response->assertRedirect(route('login'));
});
