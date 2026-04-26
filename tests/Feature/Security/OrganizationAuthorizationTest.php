<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

uses()->group('security', 'authorization');

beforeEach(function () {
    $owner = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $this->organization->members()->attach($owner->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    $this->repository = Repository::factory()->forOrganization($this->organization)->create();
    $this->package = Package::factory()
        ->forOrganization($this->organization)
        ->forRepository($this->repository)
        ->create();
    $this->token = AccessToken::factory()->forOrganization($this->organization)->create();
    $this->memberRow = OrganizationUser::factory()
        ->forOrganization($this->organization)
        ->member()
        ->create();

    $this->nonMember = User::factory()->create();

    $this->plainMember = User::factory()->create();
    $this->organization->members()->attach($this->plainMember->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);
});

function guardedOrgRequests(TestCase $test): array
{
    /** @var Organization $organization */
    $organization = $test->organization;
    /** @var Repository $repository */
    $repository = $test->repository;
    /** @var Package $package */
    $package = $test->package;
    /** @var AccessToken $token */
    $token = $test->token;
    /** @var OrganizationUser $memberRow */
    $memberRow = $test->memberRow;

    $slug = $organization->slug;

    return [
        'organizations.show' => ['GET', "/organizations/{$slug}", []],
        'organizations.dismiss-onboarding' => ['POST', "/organizations/{$slug}/dismiss-onboarding", []],
        'organizations.packages.index' => ['GET', "/organizations/{$slug}/packages", []],
        'organizations.packages.show' => ['GET', "/organizations/{$slug}/packages/{$package->uuid}", []],
        'organizations.repositories.index' => ['GET', "/organizations/{$slug}/repositories", []],
        'organizations.repositories.suggest' => ['GET', "/organizations/{$slug}/repositories/suggest?provider=github", []],
        'organizations.repositories.owners' => ['GET', "/organizations/{$slug}/repositories/owners?provider=github", []],
        'organizations.repositories.show' => ['GET', "/organizations/{$slug}/repositories/{$repository->uuid}", []],
        'organizations.repositories.store' => ['POST', "/organizations/{$slug}/repositories", [
            'provider' => 'github',
            'repo_identifier' => 'foo/bar',
            'default_branch' => 'main',
        ]],
        'organizations.repositories.bulk-store' => ['POST', "/organizations/{$slug}/repositories/bulk", [
            'provider' => 'github',
            'repositories' => [['repo_identifier' => 'foo/bar']],
        ]],
        'organizations.repositories.update' => ['PATCH', "/organizations/{$slug}/repositories/{$repository->uuid}", []],
        'organizations.repositories.sync' => ['POST', "/organizations/{$slug}/repositories/{$repository->uuid}/sync", []],
        'organizations.repositories.webhook.sync' => ['POST', "/organizations/{$slug}/repositories/{$repository->uuid}/webhook/sync", []],
        'organizations.security.index' => ['GET', "/organizations/{$slug}/security", []],
        'organizations.security.scan' => ['POST', "/organizations/{$slug}/security/scan", []],
        'organizations.settings.update' => ['PATCH', "/organizations/{$slug}/settings/general", ['name' => 'New Name']],
        'organizations.settings.members.store' => ['POST', "/organizations/{$slug}/settings/members", [
            'email' => 'new@example.com',
            'role' => OrganizationRole::Member->value,
        ]],
        'organizations.settings.members.update' => ['PATCH', "/organizations/{$slug}/settings/members/{$memberRow->uuid}", [
            'role' => OrganizationRole::Admin->value,
        ]],
        'organizations.settings.tokens.index' => ['GET', "/organizations/{$slug}/settings/tokens", []],
        'organizations.settings.tokens.store' => ['POST', "/organizations/{$slug}/settings/tokens", ['name' => 'pwn']],
        'organizations.settings.tokens.destroy' => ['DELETE', "/organizations/{$slug}/settings/tokens/{$token->uuid}", []],
    ];
}

it('forbids non-members from every guarded org route', function () {
    foreach (guardedOrgRequests($this) as $name => [$method, $url, $body]) {
        $response = $this->actingAs($this->nonMember)->call($method, $url, $body);

        expect($response->status())->toBe(
            403,
            "non-member should be forbidden from {$name}, got {$response->status()}"
        );
    }
});

it('forbids plain Member role from admin-only org routes', function () {
    $memberAllowed = [
        'organizations.show',
        'organizations.dismiss-onboarding',
        'organizations.packages.index',
        'organizations.packages.show',
        'organizations.repositories.index',
        'organizations.repositories.show',
        'organizations.security.index',
    ];

    foreach (guardedOrgRequests($this) as $name => [$method, $url, $body]) {
        if (in_array($name, $memberAllowed, true)) {
            continue;
        }

        $response = $this->actingAs($this->plainMember)->call($method, $url, $body);

        expect($response->status())->toBe(
            403,
            "plain member should be forbidden from {$name}, got {$response->status()}"
        );
    }
});

it('rejects token creation by a non-member and persists nothing', function () {
    $organization = Organization::factory()->create();
    $attacker = User::factory()->create();

    $this->actingAs($attacker)
        ->post("/organizations/{$organization->slug}/settings/tokens", ['name' => 'pwn'])
        ->assertForbidden();

    expect(AccessToken::where('organization_uuid', $organization->uuid)->count())->toBe(0);
});
