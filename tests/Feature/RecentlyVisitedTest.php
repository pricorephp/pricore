<?php

use App\Domains\Repository\Actions\RecordRepositoryViewTask;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageView;
use App\Models\Repository;
use App\Models\RepositoryView;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'role' => 'owner',
        'uuid' => (string) Str::uuid(),
    ]);
});

it('records a repository view when the repository page is opened', function () {
    $repository = Repository::factory()->for($this->organization)->create();

    actingAs($this->user)
        ->get(route('organizations.repositories.show', [$this->organization->slug, $repository->uuid]))
        ->assertOk();

    assertDatabaseHas('repository_views', [
        'user_uuid' => $this->user->uuid,
        'repository_uuid' => $repository->uuid,
        'view_count' => 1,
    ]);
});

it('throttles repeated repository views within a minute', function () {
    $repository = Repository::factory()->for($this->organization)->create();
    $recordRepositoryViewTask = app(RecordRepositoryViewTask::class);

    $recordRepositoryViewTask->handle($this->user, $repository);
    $recordRepositoryViewTask->handle($this->user, $repository);

    expect(RepositoryView::sole()->view_count)->toBe(1);
});

it('shares recently visited packages and repositories newest first', function () {
    $older = Package::factory()->create(['organization_uuid' => $this->organization->uuid]);
    $newer = Package::factory()->create(['organization_uuid' => $this->organization->uuid]);
    PackageView::factory()->forUser($this->user)->forPackage($older)->create(['last_viewed_at' => now()->subDay()]);
    PackageView::factory()->forUser($this->user)->forPackage($newer)->create(['last_viewed_at' => now()]);

    $repository = Repository::factory()->for($this->organization)->create();
    RepositoryView::factory()->forUser($this->user)->forRepository($repository)->create();

    actingAs($this->user)
        ->get(route('organizations.show', $this->organization->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentlyVisited.packages.0.uuid', $newer->uuid)
            ->where('recentlyVisited.packages.1.uuid', $older->uuid)
            ->where('recentlyVisited.repositories.0.uuid', $repository->uuid)
        );
});

it('excludes views from other users and other organizations', function () {
    $otherUser = User::factory()->create();
    $package = Package::factory()->create(['organization_uuid' => $this->organization->uuid]);
    PackageView::factory()->forUser($otherUser)->forPackage($package)->create();

    $foreignPackage = Package::factory()->create();
    PackageView::factory()->forUser($this->user)->forPackage($foreignPackage)->create();

    actingAs($this->user)
        ->get(route('organizations.show', $this->organization->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentlyVisited.packages', [])
            ->where('recentlyVisited.repositories', [])
        );
});
