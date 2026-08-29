<?php

use App\Domains\Repository\Actions\RecordDistArchiveAction;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->create(['name' => 'acme/test-package']);

    $this->record = function (PackageVersion $packageVersion, string $path, string $shasum) {
        return app(RecordDistArchiveAction::class)->handle(
            $packageVersion,
            new DistArchiveData(path: $path, shasum: $shasum, size: 128),
            'acme',
        );
    };
});

it('records an archive and points the version at it', function () {
    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'dist_url' => null,
        'dist_path' => null,
    ]);

    $archive = ($this->record)($packageVersion, 'acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'sha-one');

    expect($archive)->not->toBeNull()
        ->and($archive->detached_at)->toBeNull()
        ->and($archive->package_uuid)->toBe($this->package->uuid);

    $packageVersion->refresh();

    expect($packageVersion->dist_path)->toBe('acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip')
        ->and($packageVersion->dist_shasum)->toBe('sha-one')
        ->and($packageVersion->dist_size)->toBe(128)
        ->and($packageVersion->dist_url)->toBe(
            url('/acme/dists/acme/test-package/dev-main/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip')
        );
});

it('updates in place when the same reference is recorded again', function () {
    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);

    ($this->record)($packageVersion, 'acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'sha-one');
    ($this->record)($packageVersion, 'acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip', 'sha-two');

    expect(DistArchive::query()->count())->toBe(1)
        ->and(DistArchive::query()->sole()->shasum)->toBe('sha-two');
});

it('detaches the previous archive but keeps its file', function () {
    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);

    $firstPath = 'acme/acme/test-package/dev-main_aaaaaaaaaaaa.zip';
    Storage::disk('local')->put($firstPath, 'zip-one');
    ($this->record)($packageVersion, $firstPath, 'sha-one');

    // The branch moves on.
    $packageVersion->update(['source_reference' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);
    $secondPath = 'acme/acme/test-package/dev-main_bbbbbbbbbbbb.zip';
    Storage::disk('local')->put($secondPath, 'zip-two');
    ($this->record)($packageVersion, $secondPath, 'sha-two');

    $first = DistArchive::query()->where('path', $firstPath)->sole();
    $second = DistArchive::query()->where('path', $secondPath)->sole();

    expect($first->detached_at)->not->toBeNull()
        ->and($second->detached_at)->toBeNull();

    // Keeping the file is the point: lock files pinning the old commit resolve.
    Storage::disk('local')->assertExists($firstPath);
});

it('returns null when the version has no source reference', function () {
    $packageVersion = PackageVersion::factory()->for($this->package)->create([
        'version' => 'dev-main',
        'source_reference' => null,
    ]);

    expect(($this->record)($packageVersion, 'some/path.zip', 'sha'))->toBeNull()
        ->and(DistArchive::query()->count())->toBe(0);
});
