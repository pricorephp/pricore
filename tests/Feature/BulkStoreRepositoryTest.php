<?php

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\post;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function createOwnerWithOrganization(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    return [$user, $organization];
}

it('can bulk import repositories', function () {
    [$user, $organization] = createOwnerWithOrganization();

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'provider' => 'github',
            'repositories' => [
                ['repo_identifier' => 'owner/repo-1'],
                ['repo_identifier' => 'owner/repo-2'],
                ['repo_identifier' => 'owner/repo-3'],
            ],
        ])
        ->assertRedirect(route('organizations.repositories.index', $organization->slug))
        ->assertSessionHas('status');

    assertDatabaseHas('repositories', [
        'organization_uuid' => $organization->uuid,
        'repo_identifier' => 'owner/repo-1',
        'provider' => 'github',
    ]);
    assertDatabaseHas('repositories', [
        'organization_uuid' => $organization->uuid,
        'repo_identifier' => 'owner/repo-2',
    ]);
    assertDatabaseHas('repositories', [
        'organization_uuid' => $organization->uuid,
        'repo_identifier' => 'owner/repo-3',
    ]);

    Queue::assertPushed(SyncRepositoryJob::class, 3);
});

it('skips already connected repositories during bulk import', function () {
    [$user, $organization] = createOwnerWithOrganization();

    Repository::factory()->create([
        'organization_uuid' => $organization->uuid,
        'provider' => 'github',
        'repo_identifier' => 'owner/existing-repo',
    ]);

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'provider' => 'github',
            'repositories' => [
                ['repo_identifier' => 'owner/existing-repo'],
                ['repo_identifier' => 'owner/new-repo-1'],
                ['repo_identifier' => 'owner/new-repo-2'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Repository::where('organization_uuid', $organization->uuid)->count())->toBe(3);

    Queue::assertPushed(SyncRepositoryJob::class, 2);
});

it('validates that repositories array is required', function () {
    [$user, $organization] = createOwnerWithOrganization();

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'provider' => 'github',
            'repositories' => [],
        ])
        ->assertSessionHasErrors('repositories');
});

it('validates that provider is required', function () {
    [$user, $organization] = createOwnerWithOrganization();

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'repositories' => [
                ['repo_identifier' => 'owner/repo-1'],
            ],
        ])
        ->assertSessionHasErrors('provider');
});

it('validates that repo_identifier is required for each repository', function () {
    [$user, $organization] = createOwnerWithOrganization();

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'provider' => 'github',
            'repositories' => [
                ['repo_identifier' => ''],
            ],
        ])
        ->assertSessionHasErrors('repositories.0.repo_identifier');
});

it('validates max 50 repositories', function () {
    [$user, $organization] = createOwnerWithOrganization();

    $repositories = array_map(
        fn ($i) => ['repo_identifier' => "owner/repo-{$i}"],
        range(1, 51),
    );

    actingAs($user)
        ->post(route('organizations.repositories.bulk-store', $organization->slug), [
            'provider' => 'github',
            'repositories' => $repositories,
        ])
        ->assertSessionHasErrors('repositories');
});

it('redirects unauthenticated users', function () {
    $organization = Organization::factory()->create();

    post(route('organizations.repositories.bulk-store', $organization->slug), [
        'provider' => 'github',
        'repositories' => [
            ['repo_identifier' => 'owner/repo-1'],
        ],
    ])->assertRedirect(route('login'));
});
