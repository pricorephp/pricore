<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('security', 'repositories', 'webhooks');

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->repository = Repository::factory()
        ->forOrganization($this->organization)
        ->withWebhook()
        ->create(['provider' => 'git', 'repo_identifier' => 'https://git.example.com/acme/package.git']);
});

function memberWithRole(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $organization->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => $role->value,
    ]);

    return $user;
}

it('shows the manual webhook secret to organization admins and owners', function (OrganizationRole $role) {
    $this->actingAs(memberWithRole($this->organization, $role))
        ->get(route('organizations.repositories.show', [$this->organization, $this->repository]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('repository.webhookSecret', $this->repository->webhook_secret)
            ->where('canManageRepository', true)
        );
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('hides the manual webhook secret from organization members', function () {
    $this->actingAs(memberWithRole($this->organization, OrganizationRole::Member))
        ->get(route('organizations.repositories.show', [$this->organization, $this->repository]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('repository.webhookActive', true)
            ->whereNot('repository.webhookUrl', null)
            ->where('repository.webhookSecret', null)
        );
});

it('never includes webhook secrets on the repositories index', function (OrganizationRole $role) {
    $this->actingAs(memberWithRole($this->organization, $role))
        ->get(route('organizations.repositories.index', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('repositories.0.uuid', $this->repository->uuid)
            ->where('repositories.0.webhookSecret', null)
        );
})->with([OrganizationRole::Admin, OrganizationRole::Member]);
