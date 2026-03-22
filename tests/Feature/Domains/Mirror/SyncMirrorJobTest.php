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
