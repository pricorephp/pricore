<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses()->group('security', 'authorization');

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
    $this->inertiaHeaders = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '',
    ];

    $this->organization = Organization::factory()->create();
    $this->repository = Repository::factory()->forOrganization($this->organization)->create([
        'name' => 'Private repository search fixture',
    ]);
    $this->package = Package::factory()
        ->forOrganization($this->organization)
        ->forRepository($this->repository)
        ->create(['name' => 'private/search-fixture', 'description' => 'Private package description']);

    $this->user = User::factory()->create();
    // Membership elsewhere must not grant access to the requested organization.
    $otherOrganization = Organization::factory()->create();
    $otherOrganization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);
});

it('omits private search data from forbidden responses for non-members and removed members', function (bool $inertia, bool $removedMember) {
    if ($removedMember) {
        $this->organization->members()->attach($this->user->uuid, [
            'uuid' => Str::uuid()->toString(),
            'role' => OrganizationRole::Member->value,
        ]);
        $this->organization->members()->detach($this->user->uuid);
    }

    $response = $this->actingAs($this->user)->get(
        route('organizations.show', $this->organization),
        $inertia ? $this->inertiaHeaders : [],
    );

    $response->assertForbidden();
    $page = $inertia ? $response->json() : json_decode(json_encode($response->viewData('page')), true);

    expect($page['component'])->toBe('error')
        ->and($page['props']['status'])->toBe(403)
        ->and($page['props']['search']['packages'])->toBe([])
        ->and($page['props']['search']['repositories'])->toBe([]);

    $response->assertDontSee($this->package->uuid)
        ->assertDontSee($this->repository->uuid)
        ->assertDontSee('Private package description')
        ->assertDontSee('Private repository search fixture');
})->with([false, true])->with([false, true]);

it('preserves search data for organization members', function (bool $inertia) {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('organizations.show', $this->organization), $inertia ? $this->inertiaHeaders : []);
    $response->assertOk();
    $page = $inertia ? $response->json() : json_decode(json_encode($response->viewData('page')), true);

    expect($page['props']['search']['packages'])->toHaveCount(1)
        ->and($page['props']['search']['packages'][0]['uuid'])->toBe($this->package->uuid)
        ->and($page['props']['search']['packages'][0]['name'])->toBe($this->package->name)
        ->and($page['props']['search']['repositories'])->toHaveCount(1)
        ->and($page['props']['search']['repositories'][0]['uuid'])->toBe($this->repository->uuid)
        ->and($page['props']['search']['repositories'][0]['name'])->toBe($this->repository->name);
})->with([false, true]);

it('preserves permitted search data when a member cannot access organization settings', function () {
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $this->actingAs($this->user)
        ->get(route('organizations.settings.members', $this->organization))
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->has('search.packages', 1)
            ->where('search.packages.0.uuid', $this->package->uuid)
            ->has('search.repositories', 1)
            ->where('search.repositories.0.uuid', $this->repository->uuid)
        );
});
