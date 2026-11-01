<?php

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\ActivityLog;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: Organization, 1: User}
 */
function packagePathsOrganization(string $role = 'admin'): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $owner->uuid]);
    $organization->members()->attach($owner->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    if ($role === 'owner') {
        return [$organization, $owner];
    }

    $user = User::factory()->create();
    $organization->members()->attach($user->uuid, ['role' => $role, 'uuid' => (string) Str::uuid()]);

    return [$organization, $user];
}

function packageWithArchive(Organization $organization, Repository $repository, string $name, ?string $sourcePath): Package
{
    $factory = Package::factory()->forOrganization($organization)->forRepository($repository);
    $package = ($sourcePath === null ? $factory : $factory->atPath($sourcePath))->create(['name' => $name]);
    $versionFactory = PackageVersion::factory()->forPackage($package);
    $version = ($sourcePath === null ? $versionFactory : $versionFactory->atPath($sourcePath))->create();
    $archivePath = 'acme/'.str_replace('/', '-', $name).'.zip';

    Storage::disk('local')->put($archivePath, 'zip');
    DistArchive::factory()->forPackageVersion($version)->create(['path' => $archivePath]);

    return $package;
}

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
});

it('lets an admin configure package paths and starts a forced sync', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->create();

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => "packages/*\n\n ./ \n",
        ])
        ->assertRedirect(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->assertSessionHas('status', 'Package paths updated and a sync has been started.');

    expect($repository->fresh()?->package_paths)->toBe(['packages/*', '.']);

    Queue::assertPushed(SyncRepositoryJob::class, fn (SyncRepositoryJob $job) => $job->force && $job->repository->is($repository));
});

it('accepts package paths as a list', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->create();

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => ['packages/billing', 'packages/crm'],
        ])
        ->assertRedirect();

    expect($repository->fresh()?->package_paths)->toBe(['packages/billing', 'packages/crm']);
});

it('rejects package paths that escape the repository', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->create();

    actingAs($admin)
        ->from(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => "../etc\npackages/*",
        ])
        ->assertRedirect(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->assertSessionHasErrors('package_paths.0');

    expect($repository->fresh()?->package_paths)->toBeNull();

    Queue::assertNotPushed(SyncRepositoryJob::class);
});

it('forbids members from changing package paths', function () {
    [$organization, $member] = packagePathsOrganization('member');
    $repository = Repository::factory()->forOrganization($organization)->create();

    actingAs($member)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => 'packages/*',
        ])
        ->assertForbidden();

    expect($repository->fresh()?->package_paths)->toBeNull();
});

it('removes packages the new paths no longer select and purges their archives', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['.', 'packages/*'])->create();
    $root = packageWithArchive($organization, $repository, 'acme/monorepo', null);
    $billing = packageWithArchive($organization, $repository, 'acme/billing', 'packages/billing');

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => 'packages/billing',
        ])
        ->assertSessionHas('status', 'Package paths updated and a sync has been started. Removed 1 package outside the configured paths.');

    expect(Package::query()->find($root->uuid))->toBeNull()
        ->and(Package::query()->find($billing->uuid))->not->toBeNull()
        ->and(ActivityLog::query()->where('type', ActivityType::PackageRemoved->value)->count())->toBe(1);

    Storage::disk('local')->assertMissing('acme/acme-monorepo.zip');
    Storage::disk('local')->assertExists('acme/acme-billing.zip');
});

it('clears package paths and keeps only the root package', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['.', 'packages/*'])->create();
    $root = packageWithArchive($organization, $repository, 'acme/monorepo', null);
    $billing = packageWithArchive($organization, $repository, 'acme/billing', 'packages/billing');

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => '',
        ])
        ->assertRedirect();

    expect($repository->fresh()?->package_paths)->toBeNull()
        ->and(Package::query()->find($root->uuid))->not->toBeNull()
        ->and(Package::query()->find($billing->uuid))->toBeNull();
});

it('does nothing when the paths are unchanged', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['packages/*'])->create();
    $billing = packageWithArchive($organization, $repository, 'acme/billing', 'packages/billing');

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => "packages/*\n",
        ])
        ->assertSessionHas('status', 'Repository updated successfully.');

    expect(Package::query()->find($billing->uuid))->not->toBeNull();

    Queue::assertNotPushed(SyncRepositoryJob::class);
});

it('passes the dist setting and current paths to the edit page', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['packages/*'])->create();

    actingAs($admin)
        ->get(route('organizations.repositories.edit', [$organization->slug, $repository->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/repositories/edit')
            ->where('distEnabled', true)
            ->where('repository.packagePaths', ['packages/*'])
        );
});

it('stores package paths when connecting a repository', function () {
    Http::fake(['api.github.com/*' => Http::response(['id' => 123, 'active' => true])]);

    [$organization, $admin] = packagePathsOrganization();
    UserGitCredential::factory()->for($admin, 'user')->github()->create();

    actingAs($admin)
        ->post(route('organizations.repositories.store', $organization->slug), [
            'provider' => 'github',
            'repo_identifier' => 'acme/monorepo',
            'package_paths' => "packages/*\n.",
        ])
        ->assertRedirect(route('organizations.repositories.index', $organization->slug));

    expect(Repository::query()->where('repo_identifier', 'acme/monorepo')->sole()->package_paths)->toBe(['packages/*', '.']);
});

it('treats reordered paths as unchanged', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['packages/*', '.'])->create();

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => ".\npackages/*",
        ])
        ->assertSessionHas('status', 'Repository updated successfully.');

    Queue::assertNotPushed(SyncRepositoryJob::class);
});

it('leaves the paths alone when the request does not include them', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['packages/*'])->create();
    $billing = packageWithArchive($organization, $repository, 'acme/billing', 'packages/billing');

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [])
        ->assertRedirect();

    expect($repository->fresh()?->package_paths)->toBe(['packages/*'])
        ->and(Package::query()->find($billing->uuid))->not->toBeNull();
});

it('keeps a package that still has versions under the new paths', function () {
    [$organization, $admin] = packagePathsOrganization();
    $repository = Repository::factory()->forOrganization($organization)->withPackagePaths(['src/*', 'packages/*'])->create();
    // Moved from src/billing to packages/billing; the last synced ref was an old tag
    $billing = packageWithArchive($organization, $repository, 'acme/billing', 'src/billing');
    PackageVersion::factory()->forPackage($billing)->atPath('packages/billing')->create(['version' => 'v2.0.0']);

    actingAs($admin)
        ->patch(route('organizations.repositories.update', [$organization->slug, $repository->uuid]), [
            'package_paths' => 'packages/*',
        ])
        ->assertSessionHas('status', 'Package paths updated and a sync has been started.');

    expect(Package::query()->find($billing->uuid))->not->toBeNull()
        ->and($repository->fresh()?->full_sync_requested_at)->not->toBeNull();
});
