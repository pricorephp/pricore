<?php

use App\Domains\Repository\Actions\CollectRefsAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Actions\CreateSyncLogAction;
use App\Domains\Repository\Actions\FilterChangedRefsAction;
use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.github.com/repos/*/tags*' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
        ]),
        'api.github.com/repos/*/branches*' => Http::response([]),
        'api.github.com/repos/*' => Http::response(['name' => 'test-repo', 'full_name' => 'owner/test-repo']),
    ]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $this->repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()->for($user, 'user')->github()->create();

    $package = Package::factory()->forOrganization($organization)->forRepository($this->repository)->create();
    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'v1.0.0',
        'source_reference' => 'abc123',
    ]);

    $this->runJob = fn (bool $force) => (new SyncRepositoryJob($this->repository, force: $force))->handle(
        app(CreateSyncLogAction::class),
        app(CollectRefsAction::class),
        app(CreateGitCloneAction::class),
        app(FilterChangedRefsAction::class),
        app(RemoveStaleVersionsAction::class),
    );
});

it('leaves unchanged refs out of the batch by default', function () {
    Bus::fake();

    ($this->runJob)(false);

    Bus::assertNothingBatched();
});

it('syncs every ref when forced', function () {
    Bus::fake();

    ($this->runJob)(true);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
});
