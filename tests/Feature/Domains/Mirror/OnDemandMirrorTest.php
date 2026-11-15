<?php

use App\Domains\Mirror\Actions\ImportMirrorPackageAction;
use App\Domains\Mirror\Contracts\Enums\MirrorSyncMode;
use App\Domains\Mirror\Jobs\RefreshMirrorPackageJob;
use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Mirror\Jobs\SyncMirrorVersionJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\AccessToken;
use App\Models\Mirror;
use App\Models\MirrorSyncLog;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\User;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake([RefreshMirrorPackageJob::class]);

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->mirror = Mirror::factory()->onDemand()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://packagist.example.com',
        'mirror_dist' => true,
        'lazy_dist' => false,
        'sync_status' => RepositorySyncStatus::Pending,
    ]);

    // Http::fake() merges stubs rather than replacing them, so the upstream
    // state lives here and the stub reads it on every request.
    $this->upstream = new stdClass;
    $this->upstream->status = 200;
    $this->upstream->overrides = [];
    $this->upstream->versions = [
        ['name' => 'vendor/pkg', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0', 'dist' => ['type' => 'zip', 'url' => 'https://packagist.example.com/dists/vendor/pkg/1.0.0.zip', 'reference' => str_repeat('a', 40)]],
        ['name' => 'vendor/pkg', 'version' => '1.1.0', 'version_normalized' => '1.1.0.0', 'dist' => ['type' => 'zip', 'url' => 'https://packagist.example.com/dists/vendor/pkg/1.1.0.zip', 'reference' => str_repeat('b', 40)]],
    ];

    $upstream = $this->upstream;

    Http::fake(function (Request $request) use ($upstream) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($override = $upstream->overrides[$request->url()] ?? null) {
            return $override();
        }

        return match (true) {
            $path === '/packages.json' => Http::response(['metadata-url' => '/p2/%package%.json']),
            $upstream->status !== 200 => Http::response('Upstream error', $upstream->status),
            $path === '/p2/vendor/pkg.json' => Http::response([
                'minified' => 'composer/2.0',
                'packages' => ['vendor/pkg' => MetadataMinifier::minify($upstream->versions)],
            ]),
            str_starts_with($path, '/dists/') => Http::response('zip-contents'),
            default => Http::response('Not Found', 404),
        };
    });

    $plainToken = Str::random(64);
    AccessToken::factory()->withPlainToken($plainToken)->neverExpires()->forOrganization($this->organization)->create();
    $this->composerHeaders = ['Authorization' => "Bearer {$plainToken}"];

    $this->metadataRequestCount = fn (string $packageName): int => Http::recorded(
        fn (Request $request) => str_contains($request->url(), "/p2/{$packageName}.json"),
    )->count();
});

it('leaves the package list out of packages.json when an on-demand mirror exists', function () {
    Package::factory()->forOrganization($this->organization)->create(['name' => 'acme/private']);

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/packages.json')
        ->assertOk()
        ->assertJsonMissingPath('available-packages');

    $this->mirror->update(['sync_mode' => MirrorSyncMode::Full]);

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/packages.json')
        ->assertOk()
        ->assertJsonPath('available-packages', ['acme/private']);
});

it('serves a fresh packages.json to clients that cached it before an on-demand mirror was added', function () {
    $this->mirror->delete();
    Package::factory()->forOrganization($this->organization)->create(['name' => 'acme/private']);

    $lastModified = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/packages.json')
        ->assertOk()
        ->headers->get('Last-Modified');

    $this->travel(1)->minute();

    Mirror::factory()->onDemand()->create(['organization_uuid' => $this->organization->uuid]);

    $this->withHeaders([...$this->composerHeaders, 'If-Modified-Since' => $lastModified])
        ->getJson('/acme/packages.json')
        ->assertOk()
        ->assertJsonMissingPath('available-packages');
});

it('imports an unknown package on its first metadata request', function () {
    $response = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();

    expect(MetadataMinifier::expand($response->json('packages')['vendor/pkg']))->toHaveCount(2);

    $package = Package::query()->where('name', 'vendor/pkg')->sole();

    expect($package->mirror_uuid)->toBe($this->mirror->uuid)
        ->and($package->organization_uuid)->toBe($this->organization->uuid)
        ->and($package->versions()->count())->toBe(2);
});

it('advertises archives that are downloaded on their first install', function () {
    $response = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();

    $reference = str_repeat('b', 40);
    $versions = collect(MetadataMinifier::expand($response->json('packages')['vendor/pkg']))->keyBy('version');

    expect($versions['1.1.0']['dist']['url'])->toBe(url("/acme/dists/vendor/pkg/1.1.0/{$reference}.zip"));

    $this->withHeaders($this->composerHeaders)
        ->get("/acme/dists/vendor/pkg/1.1.0/{$reference}.zip")
        ->assertOk()
        ->assertStreamedContent('zip-contents');
});

it('imports an unknown package on its first dev metadata request', function () {
    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg~dev.json')
        ->assertOk();

    expect(Package::query()->where('name', 'vendor/pkg')->exists())->toBeTrue();
});

it('remembers names the mirror does not have', function () {
    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/missing.json')
        ->assertNotFound();

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/missing.json')
        ->assertNotFound();

    expect(($this->metadataRequestCount)('vendor/missing'))->toBe(1)
        ->and(Package::query()->count())->toBe(0);
});

it('returns a bad gateway when the mirror fails to answer', function () {
    $this->upstream->status = 500;

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertStatus(502);

    // A failure is not remembered as a missing package.
    $this->upstream->status = 200;

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();
});

it('returns a bad gateway when the mirror cannot be reached', function () {
    $this->upstream->overrides['https://packagist.example.com/p2/vendor/pkg.json'] = fn () => throw new ConnectionException('Connection refused');

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertStatus(502);
});

it('looks a missed name up again once another on-demand mirror is added', function () {
    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/other.json')
        ->assertNotFound();

    Mirror::factory()->onDemand()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://second.example.com',
    ]);

    $this->upstream->overrides['https://second.example.com/packages.json'] = fn () => Http::response(['metadata-url' => '/p2/%package%.json']);
    $this->upstream->overrides['https://second.example.com/p2/vendor/other.json'] = fn () => Http::response([
        'minified' => 'composer/2.0',
        'packages' => ['vendor/other' => MetadataMinifier::minify([
            ['name' => 'vendor/other', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0'],
        ])],
    ]);

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/other.json')
        ->assertOk();
});

it('serves packages the organization already has without asking the mirror', function () {
    $package = Package::factory()->forOrganization($this->organization)->create(['name' => 'vendor/pkg']);
    PackageVersion::factory()->create([
        'package_uuid' => $package->uuid,
        'version' => '9.0.0',
        'normalized_version' => '9.0.0.0',
    ]);

    $response = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();

    expect(MetadataMinifier::expand($response->json('packages')['vendor/pkg']))->toHaveCount(1)
        ->and(($this->metadataRequestCount)('vendor/pkg'))->toBe(0);

    Queue::assertNotPushed(RefreshMirrorPackageJob::class);
});

it('does not import packages for organizations without an on-demand mirror', function () {
    $this->mirror->update(['sync_mode' => MirrorSyncMode::Full]);

    $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertNotFound();

    expect(($this->metadataRequestCount)('vendor/pkg'))->toBe(0);
});

it('refreshes an imported package in the background once it is stale', function () {
    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();
    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();

    Queue::assertNotPushed(RefreshMirrorPackageJob::class);

    $package = Package::query()->where('name', 'vendor/pkg')->sole();
    Cache::forget("mirror-package-fresh:{$package->uuid}");

    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();
    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();

    Queue::assertPushed(RefreshMirrorPackageJob::class, 1);
});

it('adds new versions and removes deleted ones when refreshing a package', function () {
    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();

    $package = Package::query()->where('name', 'vendor/pkg')->sole();

    $this->upstream->versions = [
        $this->upstream->versions[1],
        ['name' => 'vendor/pkg', 'version' => '2.0.0', 'version_normalized' => '2.0.0.0', 'dist' => ['type' => 'zip', 'url' => 'https://packagist.example.com/dists/vendor/pkg/2.0.0.zip', 'reference' => str_repeat('c', 40)]],
    ];

    // The job class is faked for the metadata requests, so it is run directly.
    (new RefreshMirrorPackageJob($package))->handle(app(ImportMirrorPackageAction::class));

    expect($package->versions()->orderBy('version')->pluck('version')->all())->toBe(['1.1.0', '2.0.0']);
});

it('syncs only the imported packages of an on-demand mirror', function () {
    Bus::fake(SyncMirrorVersionJob::class);

    SyncMirrorJob::dispatchSync($this->mirror);

    expect($this->mirror->refresh()->sync_status)->toBe(RepositorySyncStatus::Ok);

    $this->withHeaders($this->composerHeaders)->getJson('/acme/p2/vendor/pkg.json')->assertOk();

    $this->travel(1)->minute();

    SyncMirrorJob::dispatchSync($this->mirror);

    $syncLog = MirrorSyncLog::query()->where('mirror_uuid', $this->mirror->uuid)->latest()->first();

    expect($syncLog->details['packages_found'])->toBe(1)
        ->and($syncLog->details['versions_found'])->toBe(2);
});

it('can create an on-demand mirror', function () {
    Queue::fake([SyncMirrorJob::class]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Packagist',
            'url' => 'https://repo.packagist.org',
            'sync_mode' => 'on_demand',
            'auth_type' => 'none',
            'mirror_dist' => true,
        ])
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    expect(Mirror::query()->where('name', 'Packagist')->sole()->sync_mode)->toBe(MirrorSyncMode::OnDemand);
});
