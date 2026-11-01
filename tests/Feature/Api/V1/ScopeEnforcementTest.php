<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    joinOrganization($this->organization, $this->user, OrganizationRole::Owner);
});

it('allows a request when the token carries the required scope', function () {
    $token = personalAccessToken($this->user, [TokenScope::ReadRepositories]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")
        ->assertOk();
});

it('rejects a request when the token lacks the required scope', function () {
    $token = personalAccessToken($this->user, [TokenScope::ReadPackages]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")
        ->assertForbidden()
        ->assertJson(['required_scope' => 'read:repositories']);
});

it('requires a delete scope for destructive operations', function () {
    // Has write but not delete — must not be able to delete.
    $token = personalAccessToken($this->user, [TokenScope::WriteRepositories]);
    $repository = Repository::factory()->create(['organization_uuid' => $this->organization->uuid]);

    $this->withToken($token)
        ->deleteJson("/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}")
        ->assertForbidden()
        ->assertJson(['required_scope' => 'delete:repositories']);
});
