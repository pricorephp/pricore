<?php

use App\Domains\Composer\Contracts\Data\VersionMetadataData;
use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
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

    $this->syncBranch = function (string $commit, bool $archiveSucceeds = true): string {
        $provider = Mockery::mock(GitProviderInterface::class);

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

it('clears the dist pointer when the branch moves but the archive cannot be built', function () {
    $firstCommit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $secondCommit = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    ($this->syncBranch)($firstCommit);

    $packageVersion = PackageVersion::query()->sole();
    expect($packageVersion->dist_shasum)->toBe(sha1("zip-for-{$firstCommit}"));

    // The branch moves, but the provider cannot produce an archive this time.
    ($this->syncBranch)($secondCommit, archiveSucceeds: false);

    $packageVersion->refresh();

    expect($packageVersion->source_reference)->toBe($secondCommit)
        ->and($packageVersion->dist_url)->toBeNull()
        ->and($packageVersion->dist_path)->toBeNull()
        ->and($packageVersion->dist_shasum)->toBeNull()
        ->and($packageVersion->dist_size)->toBeNull();

    // Composer must fall back to source rather than being handed the previous
    // commit's archive under the new reference.
    expect(VersionMetadataData::fromPackageVersion($packageVersion)->toArray())
        ->not->toHaveKey('dist');
});
