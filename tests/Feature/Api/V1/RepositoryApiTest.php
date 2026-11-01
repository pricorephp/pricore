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

it('stores monorepo package paths', function () {
    Queue::fake();

    $token = personalAccessToken($this->user, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/repositories", [
            'provider' => 'git',
            'repo_identifier' => 'https://example.com/acme/monorepo.git',
            'package_paths' => ['packages/*'],
        ])
        ->assertCreated();

    expect(Repository::query()->where('repo_identifier', 'https://example.com/acme/monorepo.git')->firstOrFail()->package_paths)
        ->toBe(['packages/*']);
});

it('does not let an organization token add repositories with Git provider credentials', function () {
    Queue::fake();

    $token = organizationAccessToken($this->organization, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/repositories", [
            'provider' => 'github',
            'repo_identifier' => 'acme/widgets',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->postJson("/api/v1/organizations/{$this->organization->slug}/repositories/bulk", [
            'provider' => 'github',
            'repositories' => [['repo_identifier' => 'acme/widgets']],
        ])
        ->assertForbidden();

    assertDatabaseMissing('repositories', ['repo_identifier' => 'acme/widgets']);
});

it('updates the package paths and starts a full sync', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['organization_uuid' => $this->organization->uuid, 'package_paths' => null]);

    $token = personalAccessToken($this->user, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}", [
            'package_paths' => ['packages/*'],
        ])
        ->assertOk()
        ->assertJsonPath('packagePaths', ['packages/*']);

    expect($repository->refresh()->package_paths)->toBe(['packages/*']);

    Queue::assertPushed(SyncRepositoryJob::class, fn (SyncRepositoryJob $job) => $job->force);
});

it('leaves the package paths alone when they are omitted', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['organization_uuid' => $this->organization->uuid, 'package_paths' => ['packages/*']]);

    $token = personalAccessToken($this->user, [TokenScope::WriteRepositories]);

    $this->withToken($token)
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}", [])
        ->assertOk()
        ->assertJsonPath('packagePaths', ['packages/*']);

    Queue::assertNothingPushed();
});

it('forbids a plain member from updating package paths', function () {
    $repository = Repository::factory()->create(['organization_uuid' => $this->organization->uuid, 'package_paths' => null]);

    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);

    $this->withToken(personalAccessToken($member, [TokenScope::WriteRepositories]))
        ->patchJson("/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}", [
            'package_paths' => ['packages/*'],
        ])
        ->assertForbidden();

    expect($repository->refresh()->package_paths)->toBeNull();
});

it('shows the manual webhook secret only to members who manage repositories', function () {
    $repository = Repository::factory()
        ->forOrganization($this->organization)
        ->withWebhook()
        ->create(['provider' => 'git', 'repo_identifier' => 'https://git.example.com/acme/package.git']);

    $member = User::factory()->create();
    joinOrganization($this->organization, $member, OrganizationRole::Member);

    $url = "/api/v1/organizations/{$this->organization->slug}/repositories/{$repository->uuid}";

    $this->withToken(personalAccessToken($this->user, [TokenScope::ReadRepositories]))
        ->getJson($url)
        ->assertJsonPath('webhookSecret', $repository->webhook_secret);

    $this->withToken(personalAccessToken($member, [TokenScope::ReadRepositories]))
        ->getJson($url)
        ->assertJsonPath('webhookSecret', null);

    $this->withToken(personalAccessToken($this->user, [TokenScope::ReadRepositories]))
        ->getJson("/api/v1/organizations/{$this->organization->slug}/repositories")
        ->assertJsonPath('data.0.webhookSecret', null);
});
