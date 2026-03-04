<?php

use App\Domains\Repository\Actions\CreateDistArchiveAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('creates a dist archive and returns path and shasum', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $package = Package::factory()->for($organization, 'organization')->create(['name' => 'acme/test-package']);
    $version = PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'source_reference' => 'abc123def456789012345678901234567890abcd',
    ]);

    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('downloadArchive')
        ->once()
        ->andReturnUsing(function (string $ref, string $outputPath) {
            file_put_contents($outputPath, 'fake-zip-content');

            return true;
        });

    $action = new CreateDistArchiveAction;
    $result = $action->handle($provider, $version, 'acme');

    expect($result)->not->toBeNull()
        ->and($result['shasum'])->toBe(sha1('fake-zip-content'))
        ->and($result['path'])->toContain('acme/acme/test-package/1.0.0_abc123def456.zip');

    Storage::disk('local')->assertExists($result['path']);
});

it('returns null when provider fails to download archive', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $package = Package::factory()->for($organization, 'organization')->create(['name' => 'acme/test-package']);
    $version = PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'source_reference' => 'abc123def456789012345678901234567890abcd',
    ]);

    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('downloadArchive')
        ->once()
        ->andReturn(false);

    $action = new CreateDistArchiveAction;
    $result = $action->handle($provider, $version, 'acme');

    expect($result)->toBeNull();
});
