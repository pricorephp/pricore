<?php

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Package\Actions\PublishArtifactAction;
use App\Domains\Package\Contracts\Enums\ArtifactPublishResult;
use App\Domains\Package\Exceptions\ArtifactRejectedException;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\ActivityLog;
use App\Models\DistArchive;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->publishArtifactAction = app(PublishArtifactAction::class);

    $this->directory = sys_get_temp_dir().'/pricore-publish-'.bin2hex(random_bytes(6));
    mkdir($this->directory);

    $this->makeArchive = function (array $composerJson, array $extraEntries = []): string {
        $path = $this->directory.'/'.bin2hex(random_bytes(4)).'.zip';
        createTestZip($path, ['composer.json' => json_encode($composerJson), ...$extraEntries]);

        return $path;
    };

    $this->publish = fn (string $archivePath, array $arguments = []) => $this->publishArtifactAction->handle(...[
        'organization' => $this->organization,
        'archivePath' => $archivePath,
        ...$arguments,
    ]);
});

afterEach(function () {
    foreach (glob($this->directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->directory);
});

it('creates an uploaded package and serves the archive as its dist', function () {
    $archive = ($this->makeArchive)(
        ['name' => 'acme/legacy', 'version' => '1.2.0', 'description' => 'Legacy SDK', 'type' => 'moodle-mod'],
        ['README.md' => '# Legacy SDK'],
    );

    $published = ($this->publish)($archive, ['allowCreatingPackage' => true]);

    $shasum = sha1_file($archive);
    $package = $published->package->refresh();
    $version = $published->version;

    expect($published->result)->toBe(ArtifactPublishResult::Added)
        ->and($published->packageCreated)->toBeTrue()
        ->and($package->is_artifact)->toBeTrue()
        ->and($package->repository_uuid)->toBeNull()
        ->and($package->description)->toBe('Legacy SDK')
        ->and($package->type)->toBe('moodle-mod')
        ->and($version->version)->toBe('1.2.0')
        ->and($version->normalized_version)->toBe('1.2.0.0')
        ->and($version->source_reference)->toBe($shasum)
        ->and($version->source_url)->toBeNull()
        ->and($version->readme)->toBe('# Legacy SDK')
        ->and($version->dist_shasum)->toBe($shasum)
        ->and($version->dist_size)->toBe(filesize($archive))
        ->and($version->dist_url)->toBe(url("/acme/dists/acme/legacy/1.2.0/{$shasum}.zip"));

    Storage::disk('local')->assertExists($version->dist_path);
    expect(Storage::disk('local')->get($version->dist_path))->toBe(file_get_contents($archive))
        ->and(DistArchive::query()->where('package_version_uuid', $version->uuid)->count())->toBe(1);

    expect(ActivityLog::query()->where('type', ActivityType::PackageCreated)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('type', ActivityType::PackageVersionUploaded)->first()?->properties)
        ->toMatchArray(['name' => 'acme/legacy', 'version' => '1.2.0', 'shasum' => $shasum]);

    Queue::assertPushed(ScanPackageVersionsJob::class);
});

it('removes keys Pricore sets itself from the stored composer.json', function () {
    $archive = ($this->makeArchive)([
        'name' => 'acme/legacy',
        'version' => '1.0.0',
        'source' => ['type' => 'git', 'url' => 'https://evil.example/repo.git', 'reference' => 'abc'],
        'dist' => ['type' => 'zip', 'url' => 'https://evil.example/legacy.zip'],
        'notification-url' => 'https://evil.example/notify',
        'require' => ['php' => '^8.2'],
    ]);

    $version = ($this->publish)($archive, ['allowCreatingPackage' => true])->version;

    expect($version->composer_json)->toBe(['name' => 'acme/legacy', 'require' => ['php' => '^8.2']]);
});

it('prefers the given version over the one in composer.json', function () {
    $archive = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0']);

    $version = ($this->publish)($archive, ['version' => '2.0.0', 'allowCreatingPackage' => true])->version;

    expect($version->version)->toBe('2.0.0');
});

it('rejects uploads it cannot place', function (array $composerJson, ?string $version, string $message) {
    $archive = ($this->makeArchive)($composerJson);

    expect(fn () => ($this->publish)($archive, ['version' => $version, 'allowCreatingPackage' => true]))
        ->toThrow(ArtifactRejectedException::class, $message);

    expect(Package::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'no version' => [['name' => 'acme/legacy'], null, 'No version given'],
    'no name' => [['version' => '1.0.0'], null, 'missing required field: name'],
    'traversal in the name' => [['name' => '../other/pkg'], '1.0.0', 'not a valid package name'],
    'uppercase name' => [['name' => 'Acme/Legacy'], '1.0.0', 'not a valid package name'],
    'slash in the version' => [['name' => 'acme/legacy'], 'dev-feature/x', 'not a valid version'],
    'traversal in the version' => [['name' => 'acme/legacy'], '..', 'not a valid version'],
    'unparseable version' => [['name' => 'acme/legacy'], 'not-a-version', 'not a valid version'],
]);

it('only creates packages when allowed', function () {
    $archive = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0']);

    expect(fn () => ($this->publish)($archive))
        ->toThrow(fn (ArtifactRejectedException $e) => expect($e->status)->toBe(404));

    expect(Package::query()->count())->toBe(0);
});

it('refuses packages synced from a repository or mirror', function (string $source) {
    $factory = Package::factory()->forOrganization($this->organization);

    match ($source) {
        'repository' => $factory
            ->forRepository(Repository::factory()->forOrganization($this->organization)->create())
            ->create(['name' => 'acme/synced']),
        'mirror' => $factory
            ->withoutRepository()
            ->create(['name' => 'acme/synced', 'mirror_uuid' => Mirror::factory()->create(['organization_uuid' => $this->organization->uuid])->uuid]),
    };

    $archive = ($this->makeArchive)(['name' => 'acme/synced', 'version' => '9.9.9']);

    expect(fn () => ($this->publish)($archive, ['allowCreatingPackage' => true]))
        ->toThrow(fn (ArtifactRejectedException $e) => expect($e->status)->toBe(409));

    expect(PackageVersion::query()->count())->toBe(0);
})->with(['repository', 'mirror']);

it('rejects an archive for a different package than the one it is uploaded to', function () {
    $package = Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/legacy']);
    $archive = ($this->makeArchive)(['name' => 'acme/other', 'version' => '1.0.0']);

    ($this->publish)($archive, ['package' => $package]);
})->throws(ArtifactRejectedException::class, 'is for acme/other, not acme/legacy');

it('treats an identical re-upload of a release as a no-op', function () {
    $archive = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0']);
    $first = ($this->publish)($archive, ['allowCreatingPackage' => true]);

    $second = ($this->publish)($archive);

    expect($second->result)->toBe(ArtifactPublishResult::Unchanged)
        ->and($second->version->uuid)->toBe($first->version->uuid)
        ->and(ActivityLog::query()->where('type', ActivityType::PackageVersionUploaded)->count())->toBe(1);
});

it('never replaces a published release', function () {
    $original = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0']);
    $first = ($this->publish)($original, ['allowCreatingPackage' => true]);

    $tampered = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0'], ['src/Backdoor.php' => '<?php']);

    expect(fn () => ($this->publish)($tampered))
        ->toThrow(fn (ArtifactRejectedException $e) => expect($e->status)->toBe(409));

    expect($first->version->refresh()->dist_shasum)->toBe(sha1_file($original))
        ->and(Storage::disk('local')->allFiles())->toBe([$first->version->dist_path]);
});

it('rejects a release that already exists under an equivalent version', function () {
    ($this->publish)(($this->makeArchive)(['name' => 'acme/legacy', 'version' => 'v1.0.0']), ['allowCreatingPackage' => true]);

    ($this->publish)(($this->makeArchive)(['name' => 'acme/legacy', 'version' => '1.0.0', 'description' => 'changed']));
})->throws(ArtifactRejectedException::class, 'already exists as v1.0.0');

it('replaces dev versions and keeps the previous archive for existing lock files', function () {
    $firstBuild = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => 'dev-main']);
    $first = ($this->publish)($firstBuild, ['allowCreatingPackage' => true]);
    $firstPath = $first->version->dist_path;

    $secondBuild = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => 'dev-main'], ['CHANGELOG.md' => 'new']);
    $second = ($this->publish)($secondBuild);

    expect($second->result)->toBe(ArtifactPublishResult::Replaced)
        ->and($second->version->uuid)->toBe($first->version->uuid)
        ->and($second->version->dist_shasum)->toBe(sha1_file($secondBuild))
        ->and(DistArchive::query()->where('package_version_uuid', $second->version->uuid)->whereNotNull('detached_at')->count())->toBe(1);

    Storage::disk('local')->assertExists($firstPath);
    Storage::disk('local')->assertExists($second->version->dist_path);
});

it('reattaches the earlier archive when a replaced dev build is uploaded again', function () {
    $buildA = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => 'dev-main']);
    $buildB = ($this->makeArchive)(['name' => 'acme/legacy', 'version' => 'dev-main'], ['CHANGELOG.md' => 'b']);

    $first = ($this->publish)($buildA, ['allowCreatingPackage' => true]);
    ($this->publish)($buildB);
    $third = ($this->publish)($buildA);

    expect($third->result)->toBe(ArtifactPublishResult::Replaced)
        ->and($third->version->dist_path)->toBe($first->version->dist_path)
        ->and(DistArchive::query()->where('package_version_uuid', $third->version->uuid)->count())->toBe(2)
        ->and(DistArchive::query()->where('package_version_uuid', $third->version->uuid)->whereNull('detached_at')->value('shasum'))->toBe(sha1_file($buildA));
});
