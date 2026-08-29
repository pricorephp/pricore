<?php

use App\Domains\Repository\Actions\CleanupDistArchivesAction;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package', 'dist_keep_last_releases' => 0]);

    $this->archiveFor = function (PackageVersion $packageVersion, string $path, ?CarbonInterface $detachedAt = null) {
        Storage::disk('local')->put($path, 'zip');

        return DistArchive::factory()
            ->forPackageVersion($packageVersion)
            ->create([
                'path' => $path,
                'detached_at' => $detachedAt,
                'source_reference' => $packageVersion->source_reference,
            ]);
    };

    $this->cleanup = fn () => app(CleanupDistArchivesAction::class)->handle();
});

it('keeps detached archives when no retention window is configured', function () {
    config(['pricore.dist.keep_detached_days' => null]);

    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'bbbbbbbbbbbb',
    ]);

    $detached = DistArchive::factory()->forPackageVersion($packageVersion)->create([
        'source_reference' => 'aaaaaaaaaaaa',
        'path' => 'acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip',
        'detached_at' => now()->subYears(2),
    ]);
    Storage::disk('local')->put($detached->path, 'zip');

    $result = ($this->cleanup)();

    expect($result['detached_removed'])->toBe(0)
        ->and(DistArchive::query()->whereKey($detached->uuid)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($detached->path);
});

it('removes detached archives past the retention window', function () {
    config(['pricore.dist.keep_detached_days' => 30]);

    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'cccccccccccc',
    ]);

    $stale = ($this->archiveFor)($packageVersion, 'acme/old.zip', now()->subDays(45));
    $stale->update(['source_reference' => 'aaaaaaaaaaaa']);

    $recent = ($this->archiveFor)($packageVersion, 'acme/recent.zip', now()->subDays(5));
    $recent->update(['source_reference' => 'bbbbbbbbbbbb']);

    $result = ($this->cleanup)();

    expect($result['detached_removed'])->toBe(1)
        ->and(DistArchive::query()->whereKey($stale->uuid)->exists())->toBeFalse()
        ->and(DistArchive::query()->whereKey($recent->uuid)->exists())->toBeTrue();

    Storage::disk('local')->assertMissing('acme/old.zip');
    Storage::disk('local')->assertExists('acme/recent.zip');
});

it('measures retention from when an archive detached, not when it was built', function () {
    config(['pricore.dist.keep_detached_days' => 30]);

    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'cccccccccccc',
    ]);

    // Built long ago on a slow-moving branch, but only superseded yesterday:
    // someone's lock file may well still pin it.
    $archive = ($this->archiveFor)($packageVersion, 'acme/long-lived.zip', now()->subDay());
    $archive->update(['source_reference' => 'aaaaaaaaaaaa', 'created_at' => now()->subDays(90)]);

    ($this->cleanup)();

    expect(DistArchive::query()->whereKey($archive->uuid)->exists())->toBeTrue();
    Storage::disk('local')->assertExists('acme/long-lived.zip');
});

it('repairs archives whose version moved on without detaching them', function () {
    config(['pricore.dist.keep_detached_days' => null]);

    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'aaaaaaaaaaaa',
    ]);

    $archive = ($this->archiveFor)($packageVersion, 'acme/drifted.zip');

    $packageVersion->update([
        'dist_path' => 'acme/drifted.zip',
        'dist_url' => url('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaa.zip'),
        'dist_shasum' => 'sha',
        'dist_size' => 10,
    ]);

    // The reference moves without the funnel, mimicking a sync that died between
    // the two writes.
    $packageVersion->updateQuietly(['source_reference' => 'bbbbbbbbbbbb']);

    $result = ($this->cleanup)();

    expect($result['detached_marked'])->toBe(1)
        ->and(DistArchive::query()->whereKey($archive->uuid)->sole()->detached_at)->not->toBeNull();

    $packageVersion->refresh();

    expect($packageVersion->dist_path)->toBeNull()
        ->and($packageVersion->dist_url)->toBeNull()
        ->and($packageVersion->dist_shasum)->toBeNull()
        ->and($packageVersion->dist_size)->toBeNull();
});

it('still prunes stable releases beyond the per-package keep count', function () {
    config(['pricore.dist.keep_detached_days' => null]);

    $this->package->update(['dist_keep_last_releases' => 1]);

    foreach ([['1.0.0', '1.0.0.0'], ['2.0.0', '2.0.0.0']] as [$version, $normalized]) {
        $packageVersion = PackageVersion::factory()->for($this->package)->create([
            'version' => $version,
            'normalized_version' => $normalized,
            'source_reference' => "ref-{$version}",
            'dist_path' => "acme/{$version}.zip",
        ]);

        ($this->archiveFor)($packageVersion, "acme/{$version}.zip");
    }

    $result = ($this->cleanup)();

    expect($result['archives_removed'])->toBe(1);

    Storage::disk('local')->assertExists('acme/2.0.0.zip');
    Storage::disk('local')->assertMissing('acme/1.0.0.zip');
    expect(DistArchive::query()->count())->toBe(1);
});
