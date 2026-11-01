<?php

use App\Domains\Mcp\Servers\PricoreServer;
use App\Domains\Mcp\Tools\AddRepositoryTool;
use App\Domains\Mcp\Tools\CreateAccessTokenTool;
use App\Domains\Mcp\Tools\GetPackageTool;
use App\Domains\Mcp\Tools\ListOrganizationsTool;
use App\Domains\Mcp\Tools\ListRepositoriesTool;
use App\Domains\Mcp\Tools\ListVulnerabilitiesTool;
use App\Domains\Mcp\Tools\SearchPackagesTool;
use App\Domains\Mcp\Tools\SyncRepositoryTool;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\SecurityAdvisory;
use App\Models\SecurityAdvisoryMatch;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

uses()->group('mcp');

function mcpMemberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Member): User
{
    $user = User::factory()->create();
    $organization->members()->attach($user->uuid, ['role' => $role->value, 'uuid' => (string) Str::uuid()]);

    return $user;
}

beforeEach(function () {
    Queue::fake();
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    $this->organization = Organization::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
    $this->repository = Repository::factory()->github()->forOrganization($this->organization)->create(['name' => 'billing']);
    $this->package = Package::factory()
        ->forOrganization($this->organization)
        ->forRepository($this->repository)
        ->create(['name' => 'acme/billing', 'description' => 'Invoices and payments']);
});

it('lists the organizations of the signed-in user', function () {
    $user = mcpMemberOf($this->organization, OrganizationRole::Admin);
    Organization::factory()->create(['slug' => 'other']);

    PricoreServer::actingAs($user)
        ->tool(ListOrganizationsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('organizations', 1)
            ->where('organizations.0.slug', 'acme')
            ->where('organizations.0.role', 'admin')
            ->where('organizations.0.packages_count', 1)
            ->where('organizations.0.composer_repository_url', url('/acme')));
});

it('searches packages by name or description', function () {
    $user = mcpMemberOf($this->organization);
    Package::factory()->forOrganization($this->organization)->create(['name' => 'acme/shipping', 'description' => null]);
    PackageVersion::factory()->forPackage($this->package)->create(['version' => '1.2.0', 'normalized_version' => '1.2.0.0']);

    PricoreServer::actingAs($user)
        ->tool(SearchPackagesTool::class, ['organization' => 'acme', 'query' => 'payments'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('packages', 1)
            ->where('packages.0.name', 'acme/billing')
            ->where('packages.0.latest_version', '1.2.0')
            ->where('packages.0.source', 'billing')
            ->etc());
});

it('returns an error for organizations the user is not a member of', function () {
    $user = User::factory()->create();

    PricoreServer::actingAs($user)
        ->tool(SearchPackagesTool::class, ['organization' => 'acme'])
        ->assertHasErrors(['does not exist or you are not a member of it']);
});

it('gets a package with the requirements and advisories of its latest stable version', function () {
    $user = mcpMemberOf($this->organization);
    PackageVersion::factory()->forPackage($this->package)->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0']);
    $latest = PackageVersion::factory()->forPackage($this->package)->create([
        'version' => '2.0.0',
        'normalized_version' => '2.0.0.0',
        'composer_json' => ['name' => 'acme/billing', 'require' => ['php' => '^8.4', 'guzzlehttp/guzzle' => '^7.0']],
    ]);
    PackageVersion::factory()->forPackage($this->package)->create(['version' => 'dev-main', 'normalized_version' => 'dev-main']);
    SecurityAdvisoryMatch::factory()
        ->dependency('guzzlehttp/guzzle')
        ->for(SecurityAdvisory::factory()->severity(AdvisorySeverity::High)->create(['title' => 'Header injection']), 'advisory')
        ->create(['package_version_uuid' => $latest->uuid]);

    PricoreServer::actingAs($user)
        ->tool(GetPackageTool::class, ['organization' => 'acme', 'package' => 'acme/billing'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('name', 'acme/billing')
            ->has('versions', 3)
            ->where('selected_version.version', '2.0.0')
            ->where('selected_version.require.guzzlehttp/guzzle', '^7.0')
            ->where('selected_version.advisories.0.title', 'Header injection')
            ->where('selected_version.advisories.0.affected_package', 'guzzlehttp/guzzle')
            ->etc());
});

it('reports an unknown package or version', function () {
    $user = mcpMemberOf($this->organization);

    PricoreServer::actingAs($user)
        ->tool(GetPackageTool::class, ['organization' => 'acme', 'package' => 'acme/unknown'])
        ->assertHasErrors(['Package [acme/unknown] does not exist']);

    PricoreServer::actingAs($user)
        ->tool(GetPackageTool::class, ['organization' => 'acme', 'package' => 'acme/billing', 'version' => '9.9.9'])
        ->assertHasErrors(['Version [9.9.9] of [acme/billing] does not exist']);
});

it('lists vulnerabilities of the latest stable versions, filtered by severity', function () {
    $user = mcpMemberOf($this->organization);
    $old = PackageVersion::factory()->forPackage($this->package)->create(['version' => '1.0.0', 'normalized_version' => '1.0.0.0']);
    $latest = PackageVersion::factory()->forPackage($this->package)->create(['version' => '2.0.0', 'normalized_version' => '2.0.0.0']);

    SecurityAdvisoryMatch::factory()
        ->for(SecurityAdvisory::factory()->severity(AdvisorySeverity::Critical)->create(['title' => 'Old issue']), 'advisory')
        ->create(['package_version_uuid' => $old->uuid]);
    SecurityAdvisoryMatch::factory()
        ->for(SecurityAdvisory::factory()->severity(AdvisorySeverity::Critical)->create(['title' => 'Remote code execution']), 'advisory')
        ->create(['package_version_uuid' => $latest->uuid]);
    SecurityAdvisoryMatch::factory()
        ->for(SecurityAdvisory::factory()->severity(AdvisorySeverity::Low)->create(['title' => 'Minor leak']), 'advisory')
        ->create(['package_version_uuid' => $latest->uuid]);

    PricoreServer::actingAs($user)
        ->tool(ListVulnerabilitiesTool::class, ['organization' => 'acme', 'severity' => 'critical'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('vulnerabilities', 1)
            ->where('vulnerabilities.0.title', 'Remote code execution')
            ->where('vulnerabilities.0.version', '2.0.0')
            ->etc());
});

it('keeps the most severe vulnerabilities when results are truncated', function () {
    $user = mcpMemberOf($this->organization);
    $latest = PackageVersion::factory()->forPackage($this->package)->create(['version' => '2.0.0', 'normalized_version' => '2.0.0.0']);

    SecurityAdvisory::factory()->count(200)->severity(AdvisorySeverity::Low)->create()
        ->each(fn (SecurityAdvisory $advisory) => SecurityAdvisoryMatch::factory()
            ->for($advisory, 'advisory')
            ->create(['package_version_uuid' => $latest->uuid]));
    SecurityAdvisoryMatch::factory()
        ->for(SecurityAdvisory::factory()->severity(AdvisorySeverity::Critical)->create(['title' => 'Remote code execution']), 'advisory')
        ->create(['package_version_uuid' => $latest->uuid]);

    PricoreServer::actingAs($user)
        ->tool(ListVulnerabilitiesTool::class, ['organization' => 'acme'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('vulnerabilities', 200)
            ->where('vulnerabilities.0.title', 'Remote code execution')
            ->where('truncated', true)
            ->has('note'));
});

it('lists repositories with their packages', function () {
    $user = mcpMemberOf($this->organization);

    PricoreServer::actingAs($user)
        ->tool(ListRepositoriesTool::class, ['organization' => 'acme'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('repositories', 1)
            ->where('repositories.0.name', 'billing')
            ->where('repositories.0.packages', ['acme/billing'])
            ->etc());
});

it('queues a repository sync for admins', function () {
    $user = mcpMemberOf($this->organization, OrganizationRole::Admin);

    PricoreServer::actingAs($user)
        ->tool(SyncRepositoryTool::class, ['organization' => 'acme', 'repository' => 'billing'])
        ->assertOk()
        ->assertSee('A sync of [billing] has been queued');

    expect($this->repository->fresh()->sync_status)->toBe(RepositorySyncStatus::Pending);
    Queue::assertPushed(SyncRepositoryJob::class, fn (SyncRepositoryJob $job) => $job->repository->is($this->repository));
});

it('does not let members use write tools', function (string $tool, array $arguments) {
    $user = mcpMemberOf($this->organization);

    PricoreServer::actingAs($user)
        ->tool($tool, ['organization' => 'acme', ...$arguments])
        ->assertHasErrors(['Your role in this organization does not allow this action.']);

    Queue::assertNothingPushed();
})->with([
    'sync-repository' => [SyncRepositoryTool::class, ['repository' => 'billing']],
    'add-repository' => [AddRepositoryTool::class, ['provider' => 'github', 'repo_identifier' => 'acme/shipping']],
    'create-access-token' => [CreateAccessTokenTool::class, ['name' => 'CI']],
]);

it('adds a repository and queues its first sync', function () {
    $user = mcpMemberOf($this->organization, OrganizationRole::Owner);

    PricoreServer::actingAs($user)
        ->tool(AddRepositoryTool::class, [
            'organization' => 'acme',
            'provider' => 'github',
            'repo_identifier' => 'acme/shipping',
            'package_paths' => ['packages/*'],
        ])
        ->assertOk()
        ->assertSee('Repository [shipping] was added');

    $repository = Repository::query()->where('repo_identifier', 'acme/shipping')->firstOrFail();

    expect($repository->organization_uuid)->toBe($this->organization->uuid)
        ->and($repository->credential_user_uuid)->toBe($user->uuid)
        ->and($repository->package_paths)->toBe(['packages/*']);
    Queue::assertPushed(SyncRepositoryJob::class);
});

it('validates the repository identifier', function () {
    $user = mcpMemberOf($this->organization, OrganizationRole::Owner);

    PricoreServer::actingAs($user)
        ->tool(AddRepositoryTool::class, ['organization' => 'acme', 'provider' => 'github', 'repo_identifier' => 'not a repo'])
        ->assertHasErrors();

    expect(Repository::query()->where('repo_identifier', 'not a repo')->exists())->toBeFalse();
});

it('creates an organization token and returns it once', function () {
    $user = mcpMemberOf($this->organization, OrganizationRole::Admin);

    PricoreServer::actingAs($user)
        ->tool(CreateAccessTokenTool::class, ['organization' => 'acme', 'name' => 'GitHub Actions', 'expires_in_days' => 30, 'scopes' => ['composer', 'write:packages']])
        ->assertOk()
        ->assertSee('composer config --global --auth')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('name', 'GitHub Actions')
            ->where('scopes', ['composer', 'write:packages'])
            ->where('token', fn (string $plainToken) => AccessToken::query()->where('token_hash', hash('sha256', $plainToken))->exists())
            ->etc());

    $token = AccessToken::query()->where('organization_uuid', $this->organization->uuid)->firstOrFail();

    expect($token->name)->toBe('GitHub Actions')
        ->and($token->user_uuid)->toBeNull()
        ->and($token->scopes)->toBe(['composer', 'write:packages'])
        ->and($token->expires_at?->isFuture())->toBeTrue();
});
