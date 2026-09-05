<?php

use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\SyncRefResultData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\AccessToken;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'acme',
        'owner_uuid' => $this->user->uuid,
    ]);
    $this->repository = Repository::factory()
        ->github()
        ->forOrganization($this->organization)
        ->create();

    $this->plainToken = 'test-token-'.uniqid();
    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();

    $this->syncBranch = function (string $commit): SyncRefResultData {
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
            ->andReturnUsing(function (string $ref, string $outputPath) use ($commit) {
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

it('keeps a locked branch commit installable after the branch head moves', function () {
    $lockedCommit = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $newCommit = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    expect(($this->syncBranch)($lockedCommit)->added)->toBe(1);
    expect(($this->syncBranch)($newCommit)->updated)->toBe(1);

    // The branch keeps a single row, so it now points at the new head.
    $packageVersion = PackageVersion::query()->sole();
    expect($packageVersion->version)->toBe('dev-main')
        ->and($packageVersion->source_reference)->toBe($newCommit);

    $response = test()->withHeaders([
        'Authorization' => "Bearer {$this->plainToken}",
        'Accept' => 'application/json',
    ])->get("/acme/dists/acme/test-package/dev-main/{$lockedCommit}.zip");

    $response->assertOk();

    expect($response->streamedContent())->toBe("zip-for-{$lockedCommit}");

    // Both archives are recorded; only the superseded one is detached.
    expect(DistArchive::query()->count())->toBe(2);

    expect(DistArchive::query()->where('source_reference', $lockedCommit)->sole()->detached_at)
        ->not->toBeNull();
    expect(DistArchive::query()->where('source_reference', $newCommit)->sole()->detached_at)
        ->toBeNull();

    // Metadata advertises the new head, so a fresh resolve moves forward.
    expect($packageVersion->dist_url)->toContain($newCommit);
});
