<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    joinOrganization($this->organization, $this->user, OrganizationRole::Owner);
});

it('lists repositories with a pagination envelope', function () {
    Repository::factory()->count(3)->create(['organization_uuid' => $this->organization->uuid]);

    $token = personalAccessToken($this->user, [TokenScope::ReadRepositories]);

    $this->withToken($token)
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories?per_page=2")
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('creates a repository and dispatches a sync', function () {
    Queue::fake();

    $token = personalAccessToken($this->user, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/repositories", [
            'provider' => 'git',
            'repo_identifier' => 'https://example.com/acme/widgets.git',
        ])
        ->assertCreated()
        ->assertJsonPath('provider', 'git');

    assertDatabaseHas('repositories', [
        'organization_uuid' => $this->organization->uuid,
        'repo_identifier' => 'https://example.com/acme/widgets.git',
    ]);

    Queue::assertPushed(SyncRepositoryJob::class);
});

it('deletes a repository with the delete scope', function () {
    $repository = Repository::factory()->create(['organization_uuid' => $this->organization->uuid]);

    $token = personalAccessToken($this->user, [TokenScope::DeleteRepositories]);

    $this->withToken($token)
        ->deleteJson("/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}")
        ->assertNoContent();

    assertDatabaseMissing('repositories', ['uuid' => $repository->uuid]);
});

it('forbids a member from creating repositories', function () {
    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);

    $token = personalAccessToken($member, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/repositories", [
            'provider' => 'git',
            'repo_identifier' => 'https://example.com/acme/widgets.git',
        ])
        ->assertForbidden();
});
