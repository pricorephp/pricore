<?php

use App\Domains\Repository\Actions\CreateDistArchiveAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->reference = 'abc123def456789012345678901234567890abcd';

    $this->createArchive = fn (GitProviderInterface $provider, string $packageName = 'acme/test-package', ?string $sourcePath = null) => (new CreateDistArchiveAction)->handle(
        provider: $provider,
        organizationSlug: 'acme',
        packageName: $packageName,
        version: '1.0.0',
        reference: $this->reference,
        sourcePath: $sourcePath,
    );
});

it('creates a dist archive and returns path and shasum', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('downloadArchive')
        ->once()
        ->andReturnUsing(function (string $ref, string $outputPath) {
            file_put_contents($outputPath, 'fake-zip-content');

            return true;
        });

    $result = ($this->createArchive)($provider);

    expect($result)->not->toBeNull()
        ->and($result->shasum)->toBe(sha1('fake-zip-content'))
        ->and($result->path)->toContain('acme/acme/test-package/1.0.0_abc123def456.zip')
        ->and($result->size)->toBe(strlen('fake-zip-content'));

    Storage::disk('local')->assertExists($result->path);
});

it('returns null when provider fails to download archive', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('downloadArchive')
        ->once()
        ->andReturn(false);

    expect(($this->createArchive)($provider))->toBeNull();
});

it('requests the archive of the version source path', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('downloadArchive')
        ->once()
        ->withArgs(fn (string $ref, string $outputPath, ?string $path) => $ref === $this->reference
            && $path === 'packages/billing')
        ->andReturnUsing(function (string $ref, string $outputPath) {
            file_put_contents($outputPath, 'subtree-zip');

            return true;
        });

    $result = ($this->createArchive)($provider, 'acme/billing', 'packages/billing');

    expect($result?->path)->toBe('acme/acme/billing/1.0.0_abc123def456.zip');
});
