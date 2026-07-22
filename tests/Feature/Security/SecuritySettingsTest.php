<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->owner->uuid]);
    $this->organization->members()->attach($this->owner->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

it('persists the anonymous access toggle', function () {
    $this->actingAs($this->owner)
        ->patch(route('organizations.settings.security.update', $this->organization), [
            'security_audits_enabled' => true,
            'security_notifications_enabled' => true,
            'anonymous_access_enabled' => true,
        ])
        ->assertRedirect();

    expect($this->organization->fresh()->anonymous_access_enabled)->toBeTrue();
});

it('requires the anonymous access field', function () {
    $this->actingAs($this->owner)
        ->patch(route('organizations.settings.security.update', $this->organization), [
            'security_audits_enabled' => true,
            'security_notifications_enabled' => true,
        ])
        ->assertSessionHasErrors('anonymous_access_enabled');
});

it('forbids a plain member from changing the anonymous access toggle', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $this->actingAs($member)
        ->patch(route('organizations.settings.security.update', $this->organization), [
            'security_audits_enabled' => true,
            'security_notifications_enabled' => true,
            'anonymous_access_enabled' => true,
        ])
        ->assertForbidden();

    expect($this->organization->fresh()->anonymous_access_enabled)->toBeFalse();
});
