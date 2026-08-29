<?php

use App\Domains\Mirror\Jobs\SyncMirrorVersionJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\DistArchive;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\PackageVersion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->organization = Organization::factory()->create(['slug' => 'acme']);
    $this->mirror = Mirror::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://satis.example.com',
        'mirror_dist' => true,
        'sync_status' => RepositorySyncStatus::Pending,
    ]);

    // Http::fake() merges stubs rather than replacing them, so the upstream
    // state lives here and the stub reads it on every request.
    $this->upstream = new stdClass;
    $this->upstream->reference = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $this->upstream->body = 'zip-one';

    $upstream = $this->upstream;

    Http::fake(function (Request $request) use ($upstream) {
        if (str_contains($request->url(), 'packages.json')) {
            return Http::response([
                'packages' => [
                    'vendor/pkg' => [
                        'dev-main' => [
                            'name' => 'vendor/pkg',
                            'version' => 'dev-main',
                            'dist' => [
                                'reference' => $upstream->reference,
                                'url' => 'https://satis.example.com/dists/vendor/pkg/dev-main.zip',
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response($upstream->body);
    });
});

it('re-mirrors a dev version when its upstream reference moves', function () {
    $firstReference = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $secondReference = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', 'dev-main');

    $packageVersion = PackageVersion::query()->sole();
    expect($packageVersion->dist_shasum)->toBe(sha1('zip-one'));

    $this->upstream->reference = $secondReference;
    $this->upstream->body = 'zip-two';

    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', 'dev-main');

    $packageVersion->refresh();

    // The pointer follows the new reference instead of keeping the old archive.
    expect($packageVersion->source_reference)->toBe($secondReference)
        ->and($packageVersion->dist_shasum)->toBe(sha1('zip-two'))
        ->and($packageVersion->dist_url)->toContain($secondReference);

    expect(DistArchive::query()->count())->toBe(2);

    $firstArchive = DistArchive::query()->where('source_reference', $firstReference)->sole();
    $secondArchive = DistArchive::query()->where('source_reference', $secondReference)->sole();

    expect($firstArchive->detached_at)->not->toBeNull()
        ->and($secondArchive->detached_at)->toBeNull();

    // The superseded archive stays on disk so locked commits still install.
    Storage::disk('local')->assertExists($firstArchive->path);
    Storage::disk('local')->assertExists($secondArchive->path);
});

it('does not re-download when the upstream reference is unchanged', function () {
    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', 'dev-main');

    $this->upstream->body = 'zip-replaced';

    SyncMirrorVersionJob::dispatchSync($this->mirror, 'vendor/pkg', 'dev-main');

    expect(DistArchive::query()->count())->toBe(1)
        ->and(PackageVersion::query()->sole()->dist_shasum)->toBe(sha1('zip-one'));
});
