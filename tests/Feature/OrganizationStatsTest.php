<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
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
    it('returns stats on organization show page', function () {
        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('organizations/show')
            ->has('stats')
            ->has('stats.packagesCount')
            ->has('stats.repositoriesCount')
            ->has('stats.tokensCount')
            ->has('stats.membersCount')
        );
    });

    it('counts packages correctly', function () {
        Package::factory()->count(5)->create([
            'organization_uuid' => $this->organization->uuid,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.packagesCount', 5)
        );
    });

    it('counts repositories correctly', function () {
        Repository::factory()->count(3)->create([
            'organization_uuid' => $this->organization->uuid,
            'sync_status' => RepositorySyncStatus::Ok,
        ]);

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.repositoriesCount', 3)
        );
    });

    it('counts tokens correctly', function () {
        AccessToken::factory()->count(4)->forOrganization($this->organization)->neverExpires()->create();

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.tokensCount', 4)
        );
    });

    it('counts members correctly', function () {
        // We already have 1 owner from beforeEach
        User::factory()->count(3)->create()->each(function ($user) {
            $this->organization->members()->attach($user->uuid, [
                'uuid' => Str::uuid()->toString(),
                'role' => OrganizationRole::Member->value,
            ]);
        });

        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.membersCount', 4) // 1 owner + 3 members
        );
    });

    it('handles empty organization gracefully', function () {
        $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('stats.packagesCount', 0)
            ->where('stats.repositoriesCount', 0)
            ->where('stats.tokensCount', 0)
            ->where('stats.membersCount', 1) // The owner
        );
    });
});
