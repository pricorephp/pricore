<?php

use App\Domains\Repository\Actions\RemoveDistArchiveTask;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\SyncRefResultData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->repository = Repository::factory()
        ->github()
        ->forOrganization($this->organization)
        ->create();

    $this->syncBranch = function (string $commit, bool $archiveSucceeds = true): SyncRefResultData {
        $provider = Mockery::mock(GitProviderInterface::class);
        $provider->shouldReceive('listDirectory')->andReturn([]);

        $provider->shouldReceive('getFileContent')
            ->andReturnUsing(fn (string $ref, string $path) => $path === 'composer.json'
                ? json_encode(['name' => 'acme/test-package', 'type' => 'library'])
                : null);
        $provider->shouldReceive('getRepositoryUrl')
            ->andReturn('https://github.com/acme/test-package.git');
        $provider->shouldReceive('getRepositoryIdentifier')
            ->andReturn('acme/test-package');
        $provider->shouldReceive('downloadArchive')
            ->andReturnUsing(function (string $ref, string $outputPath) use ($commit, $archiveSucceeds) {
                if (! $archiveSucceeds) {
                    return false;
                }

                file_put_contents($outputPath, "zip-for-{$commit}");

                return true;
            });

        return app(SyncRefAction::class)->handle(
            $provider,
            $this->repository,
            new RefData(name: 'main', commit: $commit),
        );
    };
});

it('keeps the branch on its previous commit until the new archive builds', function () {
    $firstCommit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $secondCommit = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    ($this->syncBranch)($firstCommit);

    $result = ($this->syncBranch)($secondCommit, archiveSucceeds: false);

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->source_reference)->toBe($firstCommit)
        ->and($packageVersion->dist_shasum)->toBe(sha1("zip-for-{$firstCommit}"))
        ->and($packageVersion->dist_failed_at)->toBeNull()
        ->and($result->updated)->toBe(0)
        ->and($result->distFailures)->toHaveCount(1)
        ->and($result->distFailures[0])->toMatchArray([
            'package' => 'acme/test-package',
            'version' => 'dev-main',
            'reference' => $secondCommit,
        ]);

    ($this->syncBranch)($secondCommit);

    $packageVersion->refresh();

    expect($packageVersion->source_reference)->toBe($secondCommit)
        ->and($packageVersion->dist_shasum)->toBe(sha1("zip-for-{$secondCommit}"));
});

it('hides a new version from the metadata until its archive builds', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    $result = ($this->syncBranch)($commit, archiveSucceeds: false);

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->dist_failed_at)->not->toBeNull()
        ->and($packageVersion->dist_url)->toBeNull()
        ->and($result->added)->toBe(0)
        ->and($result->distFailures)->toHaveCount(1);

    expect(($this->syncBranch)($commit)->skipped)->toBe(1);

    $packageVersion->refresh();

    expect($packageVersion->dist_failed_at)->toBeNull()
        ->and($packageVersion->dist_shasum)->toBe(sha1("zip-for-{$commit}"));
});

it('moves a version not published yet to the new commit', function () {
    $firstCommit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $secondCommit = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    ($this->syncBranch)($firstCommit, archiveSucceeds: false);
    ($this->syncBranch)($secondCommit, archiveSucceeds: false);

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->source_reference)->toBe($secondCommit)
        ->and($packageVersion->dist_failed_at)->not->toBeNull();
});

it('publishes versions without an archive when dist archives are disabled', function () {
    config(['pricore.dist.enabled' => false]);

    expect(($this->syncBranch)('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', archiveSucceeds: false)->added)->toBe(1)
        ->and(PackageVersion::query()->sole()->dist_url)->toBeNull();
});

it('rebuilds the archive of a version published before archives gated publishing', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    // Syncs published versions first and marked their archive failed afterwards
    config(['pricore.dist.enabled' => false]);
    ($this->syncBranch)($commit);
    config(['pricore.dist.enabled' => true]);
    PackageVersion::query()->sole()->update(['dist_failed_at' => now()]);

    expect(($this->syncBranch)($commit)->skipped)->toBe(1);

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->source_reference)->toBe($commit)
        ->and($packageVersion->dist_url)->not->toBeNull()
        ->and($packageVersion->dist_shasum)->toBe(sha1("zip-for-{$commit}"))
        ->and($packageVersion->dist_failed_at)->toBeNull();
});

it('keeps retrying an archive that fails again', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    ($this->syncBranch)($commit, archiveSucceeds: false);

    $result = ($this->syncBranch)($commit, archiveSucceeds: false);

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->dist_url)->toBeNull()
        ->and($packageVersion->dist_failed_at)->not->toBeNull()
        ->and($result->distFailures)->toHaveCount(1);
});

it('does not rebuild a dist archive that was removed on purpose', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    ($this->syncBranch)($commit);

    // Release retention removes archives through this task.
    app(RemoveDistArchiveTask::class)->handle(PackageVersion::query()->sole());

    expect(($this->syncBranch)($commit)->skipped)->toBe(1);

    $packageVersion = PackageVersion::query()->sole();

    // Left for the first download to rebuild, not the sync
    expect($packageVersion->archives()->sole()->file_removed_at)->not->toBeNull()
        ->and($packageVersion->dist_failed_at)->toBeNull();
});

it('skips an unchanged commit that already has a dist archive', function () {
    $commit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    ($this->syncBranch)($commit);

    expect(($this->syncBranch)($commit, archiveSucceeds: false)->skipped)->toBe(1)
        ->and(PackageVersion::query()->sole()->dist_shasum)->toBe(sha1("zip-for-{$commit}"));
});
