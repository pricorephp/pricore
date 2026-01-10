<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('organizations', 'stats');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

describe('organization stats', function () {
    it('returns comprehensive stats on organization show page', function () {
        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('organizations/show')
            ->has('stats')
            ->has('stats.repositoryHealth')
            ->has('stats.packageMetrics')
            ->has('stats.tokenMetrics')
            ->has('stats.memberMetrics')
            ->has('stats.activityFeed')
        );
    });

    it('calculates repository health metrics correctly', function () {
        Repository::factory()->count(3)->create([
            'organization_uuid' => $this->organization->uuid,
            'sync_status' => RepositorySyncStatus::Ok,
        ]);
        Repository::factory()->count(2)->create([
            'organization_uuid' => $this->organization->uuid,
            'sync_status' => RepositorySyncStatus::Failed,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.repositoryHealth.okCount', 3)
            ->where('stats.repositoryHealth.failedCount', 2)
            ->where('stats.repositoryHealth.successRate', 60)
        );
    });

    it('calculates package version metrics correctly', function () {
        $package = Package::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);
        PackageVersion::factory()->count(5)->create([
            'package_uuid' => $package->uuid,
        ]);
        PackageVersion::factory()->devBranch('main')->create([
            'package_uuid' => $package->uuid,
        ]);
        PackageVersion::factory()->devBranch('develop')->create([
            'package_uuid' => $package->uuid,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.packageMetrics.totalVersions', 7)
        );
    });

    it('calculates package visibility metrics', function () {
        Package::factory()->count(3)->create([
            'organization_uuid' => $this->organization->uuid,
            'visibility' => 'private',
        ]);
        Package::factory()->count(2)->create([
            'organization_uuid' => $this->organization->uuid,
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.packageMetrics.privatePackages', 3)
            ->where('stats.packageMetrics.publicPackages', 2)
        );
    });

    it('calculates token metrics with active and expired tokens', function () {
        // Active tokens (used recently, not expired)
        AccessToken::factory()->count(3)->forOrganization($this->organization)->neverExpires()->recentlyUsed()->create();

        // Expired tokens
        AccessToken::factory()->count(2)->forOrganization($this->organization)->expired()->create();

        // Unused tokens (never used, not expired)
        AccessToken::factory()->forOrganization($this->organization)->neverExpires()->create([
            'last_used_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.tokenMetrics.totalTokens', 6)
            ->where('stats.tokenMetrics.activeTokens', 3)
            ->where('stats.tokenMetrics.expiredTokens', 2)
            ->where('stats.tokenMetrics.unusedTokens', 1)
        );
    });

    it('calculates member role distribution', function () {
        // We already have 1 owner from beforeEach
        User::factory()->count(2)->create()->each(function ($user) {
            $this->organization->members()->attach($user->uuid, [
                'uuid' => Str::uuid()->toString(),
                'role' => OrganizationRole::Admin->value,
            ]);
        });
        User::factory()->count(3)->create()->each(function ($user) {
            $this->organization->members()->attach($user->uuid, [
                'uuid' => Str::uuid()->toString(),
                'role' => OrganizationRole::Member->value,
            ]);
        });

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.memberMetrics.totalMembers', 6) // 1 owner + 2 admins + 3 members
            ->where('stats.memberMetrics.ownerCount', 1)
            ->where('stats.memberMetrics.adminCount', 2)
            ->where('stats.memberMetrics.memberCount', 3)
        );
    });

    it('returns recent releases in activity feed', function () {
        $package = Package::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);
        PackageVersion::factory()->count(10)->create([
            'package_uuid' => $package->uuid,
            'released_at' => fn () => fake()->dateTimeBetween('-30 days', 'now'),
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->has('stats.activityFeed.recentReleases', 10)
        );
    });

    it('returns recent syncs in activity feed', function () {
        $repository = Repository::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);
        RepositorySyncLog::factory()->count(5)->successful()->create([
            'repository_uuid' => $repository->uuid,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->has('stats.activityFeed.recentSyncs', 5)
        );
    });

    it('handles empty organization gracefully', function () {
        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.packagesCount', 0)
            ->where('stats.repositoriesCount', 0)
            ->where('stats.tokensCount', 0)
            ->where('stats.repositoryHealth.successRate', 0)
            ->has('stats.activityFeed.recentReleases', 0)
            ->has('stats.activityFeed.recentSyncs', 0)
        );
    });

    it('counts repositories with pending sync status', function () {
        Repository::factory()->count(2)->create([
            'organization_uuid' => $this->organization->uuid,
            'sync_status' => RepositorySyncStatus::Pending,
        ]);
        Repository::factory()->create([
            'organization_uuid' => $this->organization->uuid,
            'sync_status' => null, // Never synced
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.repositoryHealth.pendingCount', 3) // 2 pending + 1 never synced
        );
    });
});
