<?php

use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Mirror\Jobs\SyncMirrorVersionJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Mirror;
use App\Models\MirrorSyncLog;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://satis.example.com',
        'sync_status' => RepositorySyncStatus::Pending,
    ]);
});

it('completes with success status when registry has no packages', function () {
    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'includes' => [
                'include/all.json' => ['sha1' => 'abc'],
            ],
        ]),
        'satis.example.com/include/all.json' => Http::response([
            'packages' => [],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $this->mirror->refresh();
    expect($this->mirror->sync_status)->toBe(RepositorySyncStatus::Ok);
    expect($this->mirror->last_synced_at)->not->toBeNull();

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->status)->toBe(SyncStatus::Success);
    expect($syncLog->completed_at)->not->toBeNull();
});

it('dispatches version jobs for available packages', function () {
    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0', 'dist' => ['reference' => 'abc']],
                    '2.0.0' => ['name' => 'vendor/pkg', 'version' => '2.0.0', 'dist' => ['reference' => 'def']],
                ],
            ],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->details['packages_found'])->toBe(1);
    expect($syncLog->details['versions_found'])->toBe(2);
});

it('skips unchanged versions and only dispatches jobs for new ones', function () {
    $this->mirror->update(['mirror_dist' => false]);

    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/pkg',
    ]);

    PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc',
    ]);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0', 'dist' => ['reference' => 'abc']],
                    '2.0.0' => ['name' => 'vendor/pkg', 'version' => '2.0.0', 'dist' => ['reference' => 'new-ref']],
                ],
            ],
        ]),
    ]);

    Bus::fake(SyncMirrorVersionJob::class);

    SyncMirrorJob::dispatchSync($this->mirror);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->versions_skipped)->toBe(1);
    expect($syncLog->details['versions_skipped_unchanged'])->toBe(1);
});

it('marks mirror as failed when registry is unreachable', function () {
    Http::fake([
        'satis.example.com/packages.json' => Http::response('Not Found', 404),
    ]);

    try {
        SyncMirrorJob::dispatchSync($this->mirror);
    } catch (MirrorSyncException) {
        // Expected
    }

    $this->mirror->refresh();
    expect($this->mirror->sync_status)->toBe(RepositorySyncStatus::Failed);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->status)->toBe(SyncStatus::Failed);
    expect($syncLog->error_message)->toContain('HTTP 404');
});

it('rejects an existing mirror that resolves to a private address at runtime', function () {
    $this->mirror->update(['url' => 'http://127.0.0.1:8080']);
    Http::fake();

    try {
        SyncMirrorJob::dispatchSync($this->mirror);
    } catch (MirrorSyncException) {
        // Expected
    }

    $this->mirror->refresh();
    expect($this->mirror->sync_status)->toBe(RepositorySyncStatus::Failed);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->status)->toBe(SyncStatus::Failed)
        ->and($syncLog->error_message)->toContain('private or reserved');

    Http::assertNothingSent();
});

it('syncs an allowlisted private mirror origin', function () {
    config()->set('pricore.mirrors.allowed_private_hosts', ['satis.internal']);
    $this->hostResolver->set('satis.internal', ['10.0.0.10']);
    $this->mirror->update(['url' => 'https://satis.internal']);

    Http::fake([
        'satis.internal/packages.json' => Http::response([
            'includes' => ['include/all.json' => ['sha1' => 'abc']],
        ]),
        'satis.internal/include/all.json' => Http::response(['packages' => []]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    expect($this->mirror->refresh()->sync_status)->toBe(RepositorySyncStatus::Ok);
    Http::assertSentCount(2);
});

it('removes stale versions during sync', function () {
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/pkg',
    ]);

    $staleVersion = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '0.1.0',
        'source_reference' => 'old',
    ]);

    PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'keep',
    ]);

    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0', 'dist' => ['reference' => 'keep']],
                ],
            ],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $this->assertDatabaseMissing('package_versions', ['uuid' => $staleVersion->uuid]);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->versions_removed)->toBe(1);
});

it('completes with empty sync when all versions are unchanged', function () {
    $this->mirror->update(['mirror_dist' => false]);

    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/pkg',
    ]);

    PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc',
    ]);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0', 'dist' => ['reference' => 'abc']],
                ],
            ],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $this->mirror->refresh();
    expect($this->mirror->sync_status)->toBe(RepositorySyncStatus::Ok);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->status)->toBe(SyncStatus::Success);
    expect($syncLog->versions_skipped)->toBe(1);
});

it('supports the Composer v2 metadata format', function () {
    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/pkg'],
        ]),
        'satis.example.com/p2/vendor/pkg.json' => Http::response([
            'minified' => 'composer/2.0',
            'packages' => [
                'vendor/pkg' => MetadataMinifier::minify([
                    ['name' => 'vendor/pkg', 'version' => '2.0.0', 'version_normalized' => '2.0.0.0', 'dist' => ['reference' => 'def']],
                    ['name' => 'vendor/pkg', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0', 'dist' => ['reference' => 'abc']],
                ]),
            ],
        ]),
        'satis.example.com/p2/vendor/pkg~dev.json' => Http::response('Not Found', 404),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->details['packages_found'])->toBe(1);
    expect($syncLog->details['versions_found'])->toBe(2);
});

it('supports includes format in packages.json', function () {
    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'includes' => [
                'include/all$abc123.json' => ['sha1' => 'abc123'],
            ],
        ]),
        'satis.example.com/include/all$abc123.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0', 'dist' => ['reference' => 'abc']],
                ],
            ],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->details['packages_found'])->toBe(1);
    expect($syncLog->details['versions_found'])->toBe(1);
});

it('keeps versions of a package whose metadata fails to load and syncs the others', function () {
    $failingPackage = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/pkg',
    ]);

    $failingPackageVersion = PackageVersion::factory()->create([
        'package_uuid' => $failingPackage->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc',
    ]);

    $otherPackage = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/other',
    ]);

    $staleVersion = PackageVersion::factory()->create([
        'package_uuid' => $otherPackage->uuid,
        'version' => '0.1.0',
        'source_reference' => 'old',
    ]);

    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/other', 'vendor/pkg'],
        ]),
        'satis.example.com/p2/vendor/other.json' => Http::response([
            'minified' => 'composer/2.0',
            'packages' => [
                'vendor/other' => MetadataMinifier::minify([
                    ['name' => 'vendor/other', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0', 'dist' => ['reference' => 'new']],
                ]),
            ],
        ]),
        'satis.example.com/p2/vendor/other~dev.json' => Http::response('Not Found', 404),
        'satis.example.com/p2/vendor/pkg.json' => Http::response('Server Error', 500),
        'satis.example.com/p2/vendor/pkg~dev.json' => Http::response([
            'minified' => 'composer/2.0',
            'packages' => [
                'vendor/pkg' => MetadataMinifier::minify([
                    ['name' => 'vendor/pkg', 'version' => 'dev-main', 'version_normalized' => 'dev-main', 'dist' => ['reference' => 'dev']],
                ]),
            ],
        ]),
    ]);

    SyncMirrorJob::dispatchSync($this->mirror);

    $this->assertDatabaseHas('package_versions', ['uuid' => $failingPackageVersion->uuid]);
    $this->assertDatabaseMissing('package_versions', ['uuid' => $staleVersion->uuid]);

    Bus::assertBatched(fn (PendingBatch $pendingBatch) => $pendingBatch->jobs
        ->map(fn (SyncMirrorVersionJob $syncMirrorVersionJob) => $syncMirrorVersionJob->packageName)
        ->all() === ['vendor/other']);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->versions_removed)->toBe(1)
        ->and($syncLog->details['packages_found'])->toBe(2)
        ->and($syncLog->details['packages_failed'])->toBe(['vendor/pkg']);
});

it('marks the sync as failed when every package fails to load', function () {
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $this->organization->uuid,
        'mirror_uuid' => $this->mirror->uuid,
        'name' => 'vendor/pkg',
    ]);

    $packageVersion = PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '1.0.0',
        'source_reference' => 'abc',
    ]);

    Bus::fake(SyncMirrorVersionJob::class);

    Http::fake([
        'satis.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/pkg', 'vendor/other'],
        ]),
        'satis.example.com/p2/*' => Http::response('Unauthorized', 401),
    ]);

    try {
        SyncMirrorJob::dispatchSync($this->mirror);
    } catch (MirrorSyncException) {
        // Expected
    }

    $this->assertDatabaseHas('package_versions', ['uuid' => $packageVersion->uuid]);
    Bus::assertNothingBatched();

    $this->mirror->refresh();
    expect($this->mirror->sync_status)->toBe(RepositorySyncStatus::Failed);

    $syncLog = MirrorSyncLog::where('mirror_uuid', $this->mirror->uuid)->latest()->first();
    expect($syncLog->status)->toBe(SyncStatus::Failed)
        ->and($syncLog->error_message)->toContain('vendor/pkg')
        ->and($syncLog->error_message)->toContain('HTTP 401')
        ->and($syncLog->details['packages_failed'])->toBe(['vendor/pkg', 'vendor/other']);
});
