<?php

use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Mirror\Jobs\SyncMirrorVersionJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\AccessToken;
use App\Models\DistArchive;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->mirror = Mirror::factory()->withLazyDist()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://satis.example.com',
        'sync_status' => RepositorySyncStatus::Pending,
    ]);

    $this->reference = str_repeat('a', 40);
    $this->upstreamDistUrl = 'https://satis.example.com/dists/vendor/pkg/1.0.0.zip';

    $this->upstream = new stdClass;
    $this->upstream->distStatus = 200;

    $upstream = $this->upstream;
    $reference = $this->reference;
    $upstreamDistUrl = $this->upstreamDistUrl;

    Http::fake(function (Request $request) use ($upstream, $reference, $upstreamDistUrl) {
        if (str_contains($request->url(), 'packages.json')) {
            return Http::response([
                'packages' => [
                    'vendor/pkg' => [
                        '1.0.0' => [
                            'name' => 'vendor/pkg',
                            'version' => '1.0.0',
                            'dist' => [
                                'type' => 'zip',
                                'reference' => $reference,
                                'url' => $upstreamDistUrl,
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response('zip-contents', $upstream->distStatus);
    });

    $plainToken = Str::random(64);
    AccessToken::factory()->withPlainToken($plainToken)->neverExpires()->forOrganization($this->organization)->create();
    $this->composerHeaders = ['Authorization' => "Bearer {$plainToken}"];

    $this->distPath = "/acme/dists/vendor/pkg/1.0.0/{$this->reference}.zip";
});

it('does not download archives during the sync', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    expect(PackageVersion::query()->sole()->dist_path)->toBeNull()
        ->and(DistArchive::query()->count())->toBe(0);

    Http::assertNotSent(fn (Request $request) => $request->url() === $this->upstreamDistUrl);
});

it('advertises the Pricore dist URL before the archive is downloaded', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $response = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();

    expect($response->json('packages')['vendor/pkg'][0]['dist'])->toMatchArray([
        'type' => 'zip',
        'url' => url($this->distPath),
        'reference' => $this->reference,
    ]);
});

it('keeps the upstream dist URL for mirrors that do not mirror archives', function () {
    $this->mirror->update(['mirror_dist' => false]);

    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $response = $this->withHeaders($this->composerHeaders)
        ->getJson('/acme/p2/vendor/pkg.json')
        ->assertOk();

    expect($response->json('packages')['vendor/pkg'][0]['dist']['url'])->toBe($this->upstreamDistUrl);
});

it('downloads the archive from upstream on the first request and serves it from storage after', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $this->withHeaders($this->composerHeaders)
        ->get($this->distPath)
        ->assertOk()
        ->assertStreamedContent('zip-contents');

    $packageVersion = PackageVersion::query()->sole();

    expect($packageVersion->dist_shasum)->toBe(sha1('zip-contents'))
        ->and(DistArchive::query()->where('source_reference', $this->reference)->count())->toBe(1);

    $this->withHeaders($this->composerHeaders)
        ->get($this->distPath)
        ->assertOk()
        ->assertStreamedContent('zip-contents');

    // packages.json for the sync, then a single archive download.
    Http::assertSentCount(2);
});

it('returns a bad gateway when the upstream archive cannot be downloaded', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $this->upstream->distStatus = 403;

    $this->withHeaders($this->composerHeaders)
        ->get($this->distPath)
        ->assertStatus(502);

    expect(DistArchive::query()->count())->toBe(0);
});

it('does not fetch a reference the version no longer points at', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $otherReference = str_repeat('b', 40);

    $this->withHeaders($this->composerHeaders)
        ->get("/acme/dists/vendor/pkg/1.0.0/{$otherReference}.zip")
        ->assertNotFound();

    Http::assertNotSent(fn (Request $request) => $request->url() === $this->upstreamDistUrl);
});

it('does not fetch archives on demand when dists are disabled', function () {
    config(['pricore.dist.enabled' => false]);

    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', '1.0.0');

    $this->withHeaders($this->composerHeaders)
        ->get($this->distPath)
        ->assertNotFound();
});

it('can create a mirror that downloads archives on demand', function () {
    Queue::fake(SyncMirrorJob::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Lazy Mirror',
            'url' => 'https://satis.example.com',
            'auth_type' => 'none',
            'mirror_dist' => true,
            'lazy_dist' => true,
        ])
        ->assertRedirect(route('organizations.settings.mirrors.index', $organization->slug));

    $mirror = Mirror::query()->where('name', 'Lazy Mirror')->sole();

    expect($mirror->mirror_dist)->toBeTrue()
        ->and($mirror->lazy_dist)->toBeTrue();
});
