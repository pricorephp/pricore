<?php

use App\Domains\Mirror\Exceptions\MirrorSyncException;
use App\Domains\Mirror\Services\RegistryClient\InlineRegistryClient;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Domains\Mirror\Services\RegistryClient\V2RegistryClient;
use App\Models\Mirror;
use App\Models\Organization;
use Composer\MetadataMinifier\MetadataMinifier;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'url' => 'https://registry.example.com',
    ]);
});

/**
 * Build a minified p2 metadata body, exactly as Pricore itself serves it.
 *
 * @param  array<int, array<string, mixed>>  $versions
 */
function fakeMetadataResponse(string $packageName, array $versions): array
{
    return [
        'minified' => 'composer/2.0',
        'packages' => [
            $packageName => MetadataMinifier::minify($versions),
        ],
    ];
}

it('detects the Composer v2 format and prioritizes it over inline packages', function () {
    Http::fake([
        'registry.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/pkg'],
            // A stray v1 "packages" key must not take precedence.
            'packages' => ['vendor/legacy' => []],
        ]),
    ]);

    $client = RegistryClientFactory::make($this->mirror);

    expect($client)->toBeInstanceOf(V2RegistryClient::class);
    expect($client->getAvailablePackages())->toBe(['vendor/pkg']);
});

it('falls back to the inline v1 client when no metadata-url is present', function () {
    Http::fake([
        'registry.example.com/packages.json' => Http::response([
            'packages' => [
                'vendor/pkg' => [
                    '1.0.0' => ['name' => 'vendor/pkg', 'version' => '1.0.0'],
                ],
            ],
        ]),
    ]);

    expect(RegistryClientFactory::make($this->mirror))
        ->toBeInstanceOf(InlineRegistryClient::class);
});

it('fetches and expands both stable and dev versions', function () {
    Http::fake([
        'registry.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/pkg'],
        ]),
        'registry.example.com/p2/vendor/pkg.json' => Http::response(fakeMetadataResponse('vendor/pkg', [
            ['name' => 'vendor/pkg', 'version' => '2.0.0', 'version_normalized' => '2.0.0.0', 'dist' => ['reference' => 'ref-2', 'url' => 'https://registry.example.com/d/2.zip']],
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0', 'dist' => ['reference' => 'ref-1', 'url' => 'https://registry.example.com/d/1.zip']],
        ])),
        'registry.example.com/p2/vendor/pkg~dev.json' => Http::response(fakeMetadataResponse('vendor/pkg', [
            ['name' => 'vendor/pkg', 'version' => 'dev-main', 'version_normalized' => 'dev-main', 'dist' => ['reference' => 'ref-dev']],
        ])),
    ]);

    $versions = RegistryClientFactory::make($this->mirror)->getPackageVersions('vendor/pkg');

    expect(array_keys($versions))->toEqualCanonicalizing(['1.0.0', '2.0.0', 'dev-main']);
    // Expansion must restore each version's own dist reference (not leak the diff).
    expect($versions['2.0.0']['dist']['reference'])->toBe('ref-2');
    expect($versions['1.0.0']['dist']['reference'])->toBe('ref-1');
    expect($versions['dev-main']['dist']['reference'])->toBe('ref-dev');
});

it('ignores a missing dev metadata file', function () {
    Http::fake([
        'registry.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'available-packages' => ['vendor/pkg'],
        ]),
        'registry.example.com/p2/vendor/pkg.json' => Http::response(fakeMetadataResponse('vendor/pkg', [
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'version_normalized' => '1.0.0.0', 'dist' => ['reference' => 'ref-1']],
        ])),
        'registry.example.com/p2/vendor/pkg~dev.json' => Http::response('Not Found', 404),
    ]);

    $versions = RegistryClientFactory::make($this->mirror)->getPackageVersions('vendor/pkg');

    expect(array_keys($versions))->toBe(['1.0.0']);
});

it('throws when a v2 registry does not advertise available-packages', function () {
    Http::fake([
        'registry.example.com/packages.json' => Http::response([
            'metadata-url' => '/p2/%package%.json',
            'list' => '/packages/list.json',
        ]),
    ]);

    RegistryClientFactory::make($this->mirror);
})->throws(MirrorSyncException::class, 'available-packages');
