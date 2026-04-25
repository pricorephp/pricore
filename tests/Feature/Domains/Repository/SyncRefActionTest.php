<?php

use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\Repository;
use Carbon\CarbonImmutable;

function mockProviderForRefSync(array $composerJson, ?CarbonImmutable $commitDate): GitProviderInterface
{
    $mock = Mockery::mock(GitProviderInterface::class);
    $mock->shouldReceive('getFileContent')
        ->with(Mockery::any(), 'composer.json')
        ->andReturn(json_encode($composerJson));
    $mock->shouldReceive('getCommitDate')->andReturn($commitDate);
    $mock->shouldReceive('getRepositoryUrl')->andReturn('git@github.com:vendor/repo.git');

    return $mock;
}

it('uses the commit date from the provider as released_at', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    $commitDate = CarbonImmutable::parse('2025-12-01T10:30:00Z');
    $provider = mockProviderForRefSync([
        'name' => 'vendor/package',
        'type' => 'library',
    ], $commitDate);

    $result = app(SyncRefAction::class)->handle(
        $provider,
        $repository,
        new RefData(name: 'v1.0.0', commit: 'abc123')
    );

    expect($result)->toBe('added');
    expect(PackageVersion::first()->released_at->equalTo($commitDate))->toBeTrue();
});

it('falls back to composer.json time when the provider returns no commit date', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    $provider = mockProviderForRefSync([
        'name' => 'vendor/package',
        'type' => 'library',
        'time' => '2025-11-15T08:00:00Z',
    ], null);

    app(SyncRefAction::class)->handle(
        $provider,
        $repository,
        new RefData(name: 'v1.0.0', commit: 'abc123')
    );

    $expected = CarbonImmutable::parse('2025-11-15T08:00:00Z');
    expect(PackageVersion::first()->released_at->equalTo($expected))->toBeTrue();
});

it('falls back to now() when neither commit date nor composer.json time is available', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    $provider = mockProviderForRefSync([
        'name' => 'vendor/package',
        'type' => 'library',
    ], null);

    CarbonImmutable::setTestNow('2026-04-23T12:00:00Z');

    app(SyncRefAction::class)->handle(
        $provider,
        $repository,
        new RefData(name: 'v1.0.0', commit: 'abc123')
    );

    $version = PackageVersion::first();
    expect($version->released_at->equalTo(CarbonImmutable::parse('2026-04-23T12:00:00Z')))->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('refreshes released_at when an existing version is updated with a new commit SHA', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()->forOrganization($organization)->create();

    $firstDate = CarbonImmutable::parse('2025-10-01T09:00:00Z');
    $provider = mockProviderForRefSync([
        'name' => 'vendor/package',
        'type' => 'library',
    ], $firstDate);

    app(SyncRefAction::class)->handle(
        $provider,
        $repository,
        new RefData(name: 'v1.0.0', commit: 'first-sha')
    );

    $secondDate = CarbonImmutable::parse('2025-12-20T14:30:00Z');
    $updatedProvider = mockProviderForRefSync([
        'name' => 'vendor/package',
        'type' => 'library',
    ], $secondDate);

    $result = app(SyncRefAction::class)->handle(
        $updatedProvider,
        $repository,
        new RefData(name: 'v1.0.0', commit: 'second-sha')
    );

    expect($result)->toBe('updated');
    expect(PackageVersion::count())->toBe(1);
    expect(PackageVersion::first()->released_at->equalTo($secondDate))->toBeTrue();
});
