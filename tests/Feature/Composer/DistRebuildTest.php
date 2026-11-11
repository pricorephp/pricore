<?php

use App\Domains\Repository\Actions\CleanupGitCloneAction;
use App\Domains\Repository\Actions\CreateDistArchiveAction;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Domains\Repository\Jobs\RebuildDistArchiveJob;
use App\Models\AccessToken;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->plainToken = 'test-token-'.uniqid();
    AccessToken::factory()
        ->forOrganization($this->organization)
        ->withPlainToken($this->plainToken)
        ->neverExpires()
        ->create();

    $repository = Repository::factory()->github()->forOrganization($this->organization)->create();

    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package', 'repository_uuid' => $repository->uuid]);

    $this->reference = str_repeat('a', 40);

    $this->packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_reference' => $this->reference,
        'dist_path' => 'acme/acme/test-package/1.0.0_aaaaaaaaaaaa.zip',
        'dist_shasum' => sha1('original'),
    ]);

    // A row whose file retention removed
    $this->archive = DistArchive::factory()->forPackageVersion($this->packageVersion)->create([
        'path' => 'acme/acme/test-package/1.0.0_aaaaaaaaaaaa.zip',
        'shasum' => sha1('original'),
        'file_removed_at' => now(),
    ]);

    $this->download = fn () => $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->get("/acme/dists/acme/test-package/1.0.0/{$this->reference}.zip");

    $this->rebuildWith = function (?string $content, ?Closure $whileBuilding = null) {
        $createDistArchive = Mockery::mock(CreateDistArchiveAction::class);
        $createDistArchive->shouldReceive('handle')->andReturnUsing(function (...$arguments) use ($content, $whileBuilding) {
            $whileBuilding && $whileBuilding(...$arguments);

            if ($content === null) {
                return null;
            }

            Storage::disk('local')->put($this->archive->path, $content);

            return new DistArchiveData(path: $this->archive->path, shasum: sha1($content), size: strlen($content));
        });

        (new RebuildDistArchiveJob($this->archive))->handle($createDistArchive, app(CreateGitCloneAction::class), app(CleanupGitCloneAction::class));
    };
});

it('asks Composer to retry while an archive whose file is gone gets rebuilt', function () {
    Queue::fake();

    $response = ($this->download)();

    $response->assertStatus(503)
        ->assertHeader('Retry-After', '60');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    Queue::assertPushed(RebuildDistArchiveJob::class, fn (RebuildDistArchiveJob $job) => $job->archive->is($this->archive));
});

it('answers 404 for a while after a rebuild failed', function () {
    Queue::fake();

    $this->archive->update(['rebuild_failed_at' => now()]);
    ($this->download)()->assertNotFound();

    $this->travel(11)->minutes();
    ($this->download)()->assertStatus(503);
});

it('answers 404 for a commit that never had an archive', function () {
    Queue::fake();

    $this->withHeaders(['Authorization' => "Bearer {$this->plainToken}"])
        ->get('/acme/dists/acme/test-package/1.0.0/'.str_repeat('b', 40).'.zip')
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('does not rebuild archives of packages without a repository', function () {
    Queue::fake();

    $this->package->update(['repository_uuid' => null]);

    ($this->download)()->assertNotFound();

    Queue::assertNothingPushed();
});

it('serves the rebuilt archive and restores the version pointers', function () {
    ($this->rebuildWith)('original');

    expect($this->archive->fresh())
        ->file_removed_at->toBeNull()
        ->rebuild_failed_at->toBeNull()
        ->shasum->toBe(sha1('original'));

    ($this->download)()->assertOk();
});

it('takes the checksum of a rebuilt archive that differs from the original', function () {
    ($this->rebuildWith)('rebuilt');

    expect($this->archive->fresh()->shasum)->toBe(sha1('rebuilt'))
        ->and($this->packageVersion->fresh()->dist_shasum)->toBe(sha1('rebuilt'));
});

it('records a failed rebuild', function () {
    ($this->rebuildWith)(null);

    expect($this->archive->fresh())
        ->rebuild_failed_at->not->toBeNull()
        ->file_removed_at->not->toBeNull();
});

it('rebuilds from the directory the package had at the archived commit', function () {
    $this->archive->update(['source_path' => 'packages/old']);
    $this->packageVersion->update(['source_path' => 'packages/new']);

    $requestedPath = null;
    ($this->rebuildWith)('original', function ($provider, $organizationSlug, $packageName, $version, $reference, $sourcePath) use (&$requestedPath) {
        $requestedPath = $sourcePath;
    });

    expect($requestedPath)->toBe('packages/old');
});

it('leaves the checksum of a version that moved on while the archive was building', function () {
    ($this->rebuildWith)('rebuilt', fn () => $this->packageVersion->update([
        'source_reference' => str_repeat('b', 40),
        'dist_shasum' => sha1('newer'),
    ]));

    expect($this->packageVersion->fresh()->dist_shasum)->toBe(sha1('newer'))
        ->and($this->archive->fresh()->shasum)->toBe(sha1('rebuilt'));
});
