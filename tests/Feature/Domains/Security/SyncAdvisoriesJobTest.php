<?php

use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Domains\Security\Jobs\SyncAdvisoriesJob;
use App\Models\AdvisorySyncMetadata;
use App\Models\Organization;
use App\Models\Package;
use App\Models\SecurityAdvisory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'name' => 'monolog/monolog',
    ]);
});

it('fetches and stores advisories from Packagist', function () {
    Queue::fake(ScanPackageVersionsJob::class);

    Http::fake([
        'packagist.org/api/security-advisories/*' => Http::response([
            'advisories' => [
                'monolog/monolog' => [
                    [
                        'advisoryId' => 'PKSA-001',
                        'packageName' => 'monolog/monolog',
                        'title' => 'Remote Code Execution',
                        'link' => 'https://example.com/advisory/1',
                        'cve' => 'CVE-2024-0001',
                        'affectedVersions' => '>=1.0,<1.5.2',
                        'sources' => [['name' => 'GitHub', 'remoteId' => 'GHSA-xxxx']],
                        'reportedAt' => '2024-01-15T00:00:00+00:00',
                        'composerRepository' => 'https://packagist.org',
                        'severity' => 'high',
                    ],
                ],
            ],
        ]),
    ]);

    SyncAdvisoriesJob::dispatchSync();

    expect(SecurityAdvisory::count())->toBe(1);

    $advisory = SecurityAdvisory::first();
    expect($advisory->advisory_id)->toBe('PKSA-001');
    expect($advisory->package_name)->toBe('monolog/monolog');
    expect($advisory->title)->toBe('Remote Code Execution');
    expect($advisory->cve)->toBe('CVE-2024-0001');
    expect($advisory->affected_versions)->toBe('>=1.0,<1.5.2');
    expect($advisory->severity->value)->toBe('high');

    $metadata = AdvisorySyncMetadata::first();
    expect($metadata->last_synced_at)->not->toBeNull();
    expect($metadata->advisories_count)->toBe(1);
});

it('updates existing advisories on subsequent sync', function () {
    Queue::fake(ScanPackageVersionsJob::class);

    SecurityAdvisory::factory()->create([
        'advisory_id' => 'PKSA-001',
        'package_name' => 'monolog/monolog',
        'title' => 'Old Title',
        'severity' => 'medium',
    ]);

    Http::fake([
        'packagist.org/api/security-advisories/*' => Http::response([
            'advisories' => [
                'monolog/monolog' => [
                    [
                        'advisoryId' => 'PKSA-001',
                        'packageName' => 'monolog/monolog',
                        'title' => 'Updated Title',
                        'affectedVersions' => '>=1.0,<2.0',
                        'severity' => 'critical',
                    ],
                ],
            ],
        ]),
    ]);

    SyncAdvisoriesJob::dispatchSync();

    expect(SecurityAdvisory::count())->toBe(1);

    $advisory = SecurityAdvisory::first();
    expect($advisory->title)->toBe('Updated Title');
    expect($advisory->severity->value)->toBe('critical');
});

it('dispatches scan jobs for organization packages after sync', function () {
    Queue::fake(ScanPackageVersionsJob::class);

    Http::fake([
        'packagist.org/api/security-advisories/*' => Http::response([
            'advisories' => [
                'some/package' => [
                    [
                        'advisoryId' => 'PKSA-002',
                        'packageName' => 'some/package',
                        'title' => 'Test Advisory',
                        'affectedVersions' => '>=1.0',
                        'severity' => 'low',
                    ],
                ],
            ],
        ]),
    ]);

    SyncAdvisoriesJob::dispatchSync();

    Queue::assertPushed(ScanPackageVersionsJob::class);
});

it('does not dispatch scan jobs when no changes', function () {
    Queue::fake(ScanPackageVersionsJob::class);

    AdvisorySyncMetadata::create([
        'last_synced_at' => now(),
        'last_updated_since' => now()->subHour()->timestamp,
        'advisories_count' => 0,
    ]);

    Http::fake([
        'packagist.org/api/security-advisories/*' => Http::response([
            'advisories' => [],
        ]),
    ]);

    SyncAdvisoriesJob::dispatchSync();

    Queue::assertNotPushed(ScanPackageVersionsJob::class);
});
