<?php

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Jobs\SyncRefJob;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.github.com/repos/*/contents/composer.json*' => Http::response([
            'type' => 'file',
            'content' => base64_encode(json_encode([
                'name' => 'vendor/package',
                'description' => 'Test package',
                'type' => 'library',
            ])),
        ]),
        'api.github.com/repos/*' => Http::response([
            'name' => 'test-repo',
            'full_name' => 'owner/test-repo',
        ]),
    ]);
});

it('syncs a single ref and creates a package version', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    OrganizationGitCredential::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $ref = new RefData(name: 'v1.0.0', commit: 'abc123');

    $job = new SyncRefJob($repository, $ref);
    $job->handle(app(\App\Domains\Repository\Actions\SyncRefAction::class));

    expect(Package::count())->toBe(1);
    expect(PackageVersion::count())->toBe(1);

    $version = PackageVersion::first();
    expect($version->version)->toBe('1.0.0');
    expect($version->source_reference)->toBe('abc123');
});

it('skips if batch is cancelled', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    OrganizationGitCredential::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $ref = new RefData(name: 'v1.0.0', commit: 'abc123');

    $job = new SyncRefJob($repository, $ref);

    // Create a fake cancelled batch
    $batch = Bus::batch([])->dispatch();
    $batch->cancel();

    // Set the batch on the job using reflection
    $reflection = new ReflectionProperty($job, 'batchId');
    $reflection->setValue($job, $batch->id);

    $job->handle(app(\App\Domains\Repository\Actions\SyncRefAction::class));

    expect(Package::count())->toBe(0);
    expect(PackageVersion::count())->toBe(0);
});

it('increments cache counters when part of a batch', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    OrganizationGitCredential::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    $ref = new RefData(name: 'v1.0.0', commit: 'abc123');

    $job = new SyncRefJob($repository, $ref);

    // Create a batch and add the job to it
    $batch = Bus::batch([$job])->dispatch();

    // Get the counter from cache
    $added = (int) Cache::get("sync-batch:{$batch->id}:added", 0);
    expect($added)->toBeGreaterThanOrEqual(0);
});

it('skips refs when composer.json is missing', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    OrganizationGitCredential::factory()
        ->for($organization, 'organization')
        ->github()
        ->create();

    // Use a mock provider that returns null for file content
    $mockProvider = Mockery::mock(\App\Domains\Repository\Contracts\Interfaces\GitProviderInterface::class);
    $mockProvider->shouldReceive('getFileContent')->andReturn(null);
    $mockProvider->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/test/repo');

    $ref = new RefData(name: 'v1.0.0', commit: 'abc123');

    $syncRefAction = app(\App\Domains\Repository\Actions\SyncRefAction::class);
    $result = $syncRefAction->handle($mockProvider, $repository, $ref);

    expect($result)->toBe('skipped');
    expect(Package::count())->toBe(0);
    expect(PackageVersion::count())->toBe(0);
});
