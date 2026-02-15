<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses()->group('organizations', 'invitations');

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->admin->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);
});

// --- Cancel invitation ---

it('admin can cancel a pending invitation', function () {
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create();

    $response = $this->actingAs($this->admin)
        ->delete("/organizations/{$this->organization->slug}/settings/invitations/{$invitation->uuid}");

    $response->assertRedirect();
    $response->assertSessionHas('status');
    expect(OrganizationInvitation::find($invitation->uuid))->toBeNull();
});

it('member cannot cancel invitation', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create();

    $response = $this->actingAs($member)
        ->delete("/organizations/{$this->organization->slug}/settings/invitations/{$invitation->uuid}");

    $response->assertForbidden();
});

it('cannot cancel invitation from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($otherOrg)
        ->create();

    $response = $this->actingAs($this->admin)
        ->delete("/organizations/{$this->organization->slug}/settings/invitations/{$invitation->uuid}");

    $response->assertNotFound();
});

// --- Resend invitation ---

it('admin can resend invitation', function () {
    Notification::fake();

    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create(['expires_at' => now()->addDay()]);

    $response = $this->actingAs($this->admin)
        ->post("/organizations/{$this->organization->slug}/settings/invitations/{$invitation->uuid}/resend");

    $response->assertRedirect();
    $response->assertSessionHas('status');

    // Expiry should be reset to 7 days from now
    $invitation->refresh();
    expect($invitation->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();

    Notification::assertSentOnDemand(
        \App\Notifications\OrganizationInvitationNotification::class,
    );
});

it('cannot resend accepted invitation', function () {
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->accepted()
        ->create();

    $response = $this->actingAs($this->admin)
        ->post("/organizations/{$this->organization->slug}/settings/invitations/{$invitation->uuid}/resend");

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// --- Accept invitation ---

it('authenticated user can accept a valid invitation', function () {
    $user = User::factory()->create();
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create([
            'email' => $user->email,
            'role' => OrganizationRole::Member->value,
        ]);

    $response = $this->actingAs($user)
        ->post("/invitations/{$invitation->token}/accept");

    $response->assertRedirect();
    expect($this->organization->members()->where('user_uuid', $user->uuid)->exists())->toBeTrue();

    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue();
});

it('user is added with correct role on acceptance', function () {
    $user = User::factory()->create();
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create([
            'email' => $user->email,
            'role' => OrganizationRole::Admin->value,
        ]);

    $this->actingAs($user)->post("/invitations/{$invitation->token}/accept");

    $pivotRole = $this->organization->members()
        ->where('user_uuid', $user->uuid)
        ->first()
        ->pivot
        ->role;

    expect($pivotRole)->toBe(OrganizationRole::Admin);
});

it('cannot accept an expired invitation', function () {
    $user = User::factory()->create();
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->expired()
        ->create(['email' => $user->email]);

    $response = $this->actingAs($user)
        ->post("/invitations/{$invitation->token}/accept");

    $response->assertRedirect();
    expect($this->organization->members()->where('user_uuid', $user->uuid)->exists())->toBeFalse();
});

it('cannot accept an already accepted invitation', function () {
    $user = User::factory()->create();
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->accepted()
        ->create(['email' => $user->email]);

    $response = $this->actingAs($user)
        ->post("/invitations/{$invitation->token}/accept");

    $response->assertRedirect();
    expect($this->organization->members()->where('user_uuid', $user->uuid)->exists())->toBeFalse();
});

it('returns error for invalid token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/invitations/invalid-token-here/accept');

    $response->assertRedirect();
});

it('unauthenticated user cannot accept invitation', function () {
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create();

    $response = $this->post("/invitations/{$invitation->token}/accept");

    $response->assertRedirect('/login');
});

// --- Show invitation page ---

it('shows invitation details for valid token', function () {
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create();

    $response = $this->get("/invitations/{$invitation->token}/accept");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('invitations/accept')
        ->has('invitation')
        ->has('token')
    );
});

it('shows error for expired invitation', function () {
    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->expired()
        ->create();

    $response = $this->get("/invitations/{$invitation->token}/accept");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('invitations/accept')
        ->has('error')
    );
});

it('shows error for invalid token on show page', function () {
    $response = $this->get('/invitations/invalid-token/accept');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('invitations/accept')
        ->has('error')
    );
});

it('handles already-member accepting invitation gracefully', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user->uuid, [
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $invitation = OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->create(['email' => $user->email]);

    $response = $this->actingAs($user)
        ->post("/invitations/{$invitation->token}/accept");

    $response->assertRedirect();
    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue();
});

// --- Members page shows invitations ---

it('members page shows pending invitations', function () {
    OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->count(2)
        ->create();

    $response = $this->actingAs($this->admin)
        ->get("/organizations/{$this->organization->slug}/settings/members");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/members')
        ->has('invitations', 2)
    );
});

it('members page does not show accepted invitations', function () {
    OrganizationInvitation::factory()
        ->forOrganization($this->organization)
        ->accepted()
        ->create();

    $response = $this->actingAs($this->admin)
        ->get("/organizations/{$this->organization->slug}/settings/members");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/members')
        ->has('invitations', 0)
    );
});
