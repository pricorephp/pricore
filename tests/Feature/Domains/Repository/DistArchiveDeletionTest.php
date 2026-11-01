<?php

use App\Domains\Repository\Actions\RemoveStaleVersionsAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Models\DistArchive;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelData\DataCollection;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'slug' => 'acme',
        'owner_uuid' => $this->user->uuid,
    ]);
    $this->organization->members()->attach($this->user->uuid, [
        'role' => 'owner',
        'uuid' => (string) Str::uuid(),
    ]);

    $this->repository = Repository::factory()
        ->github()
        ->forOrganization($this->organization)
        ->create();
    $this->package = Package::factory()
        ->for($this->organization, 'organization')
        ->forRepository($this->repository)
        ->create(['name' => 'acme/test-package']);

    $this->versionWithArchives = function (string $version, array $paths): PackageVersion {
        $packageVersion = PackageVersion::factory()->for($this->package)->create([
            'version' => $version,
            'source_reference' => "ref-{$version}",
            'dist_path' => $paths[0],
        ]);

        foreach ($paths as $index => $path) {
            Storage::disk('local')->put($path, 'zip');

            DistArchive::factory()->forPackageVersion($packageVersion)->create([
                'path' => $path,
                'source_reference' => "ref-{$version}-{$index}",
                'detached_at' => $index === 0 ? null : now(),
            ]);
        }

        return $packageVersion;
    };
});

it('deletes every archive file when a version is removed as stale', function () {
    ($this->versionWithArchives)('dev-gone', ['acme/gone-current.zip', 'acme/gone-old.zip']);
    ($this->versionWithArchives)('dev-main', ['acme/main.zip']);

    $refs = new RefsCollectionData(
        tags: new DataCollection(RefData::class, []),
        branches: new DataCollection(RefData::class, [new RefData(name: 'main', commit: 'abc')]),
        all: new DataCollection(RefData::class, [new RefData(name: 'main', commit: 'abc')]),
    );

    $removed = app(RemoveStaleVersionsAction::class)->handle($this->repository, $refs);

    expect($removed)->toBe(1);

    // Both archives for the removed version go, not only the one dist_path named.
    Storage::disk('local')->assertMissing('acme/gone-current.zip');
    Storage::disk('local')->assertMissing('acme/gone-old.zip');
    Storage::disk('local')->assertExists('acme/main.zip');

    expect(DistArchive::query()->count())->toBe(1);
});

it('deletes archive files when a package is deleted', function () {
    ($this->versionWithArchives)('dev-main', ['acme/main-current.zip', 'acme/main-old.zip']);

    $this->actingAs($this->user)
        ->delete(route('organizations.packages.destroy', [$this->organization, $this->package]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing('acme/main-current.zip');
    Storage::disk('local')->assertMissing('acme/main-old.zip');
    expect(DistArchive::query()->count())->toBe(0);
});

it('deletes archive files when a repository is deleted', function () {
    ($this->versionWithArchives)('dev-main', ['acme/main-current.zip', 'acme/main-old.zip']);

    $this->actingAs($this->user)
        ->delete(route('organizations.repositories.destroy', [$this->organization, $this->repository]))
        ->assertRedirect();

    // Packages, versions and rows all cascade in the database, so the files have
    // to be cleared before the rows vanish.
    Storage::disk('local')->assertMissing('acme/main-current.zip');
    Storage::disk('local')->assertMissing('acme/main-old.zip');
    expect(DistArchive::query()->count())->toBe(0);
});
