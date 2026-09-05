<?php

use App\Domains\Repository\Services\GitProviders\AbstractGitProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Stands in for an API provider whose archive endpoint returns the whole repository.
 */
class FixtureArchiveProvider extends AbstractGitProvider
{
    public int $downloads = 0;

    public function __construct(
        protected string $fixturePath,
        protected bool $succeeds = true,
    ) {
        parent::__construct('acme/monorepo', []);
    }

    protected function configureHttpClient(): PendingRequest
    {
        return Http::withHeaders([]);
    }

    protected function downloadFullArchive(string $ref, string $outputPath): bool
    {
        $this->downloads++;

        return $this->succeeds && copy($this->fixturePath, $outputPath);
    }

    /**
     * @return array<string, string>
     */
    public function cachedArchives(): array
    {
        return $this->fullArchives;
    }

    public function getTags(): array
    {
        return [];
    }

    public function getBranches(): array
    {
        return [];
    }

    public function getFileContent(string $ref, string $path): ?string
    {
        return null;
    }

    public function listDirectory(string $ref, string $path): array
    {
        return [];
    }

    public function validateCredentials(): bool
    {
        return true;
    }

    public function getRepositoryUrl(): string
    {
        return 'https://example.test/acme/monorepo.git';
    }

    public function createWebhook(string $url, string $secret): array
    {
        return ['id' => 1];
    }

    public function deleteWebhook(int|string $hookId): void {}
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-subtree-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
    $this->fixture = $this->directory.'/full.zip';

    createTestZip($this->fixture, [
        'acme-monorepo-abc1234/composer.json' => '{"name": "acme/monorepo"}',
        'acme-monorepo-abc1234/packages/billing/composer.json' => '{"name": "acme/billing"}',
        'acme-monorepo-abc1234/packages/billing/src/Invoice.php' => '<?php',
        'acme-monorepo-abc1234/packages/crm/composer.json' => '{"name": "acme/crm"}',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('cuts the subdirectory out of the full archive and re-roots it', function () {
    $provider = new FixtureArchiveProvider($this->fixture);
    $output = $this->directory.'/billing.zip';

    expect($provider->downloadArchive('abc123def4567890', $output, 'packages/billing'))->toBeTrue()
        ->and(testZipFileNames($output))->toBe([
            'billing-abc123def456/composer.json',
            'billing-abc123def456/src/Invoice.php',
        ]);
});

it('downloads the full archive once for sibling packages at the same ref', function () {
    $provider = new FixtureArchiveProvider($this->fixture);

    $provider->downloadArchive('abc123def4567890', $this->directory.'/billing.zip', 'packages/billing');
    $provider->downloadArchive('abc123def4567890', $this->directory.'/crm.zip', 'packages/crm');

    expect($provider->downloads)->toBe(1)
        ->and(testZipFileNames($this->directory.'/crm.zip'))->toBe(['crm-abc123def456/composer.json']);
});

it('downloads again for a different ref', function () {
    $provider = new FixtureArchiveProvider($this->fixture);

    $provider->downloadArchive('abc123def4567890', $this->directory.'/billing-a.zip', 'packages/billing');
    $provider->downloadArchive('fed321cba0987654', $this->directory.'/billing-b.zip', 'packages/billing');

    expect($provider->downloads)->toBe(2)
        ->and(testZipFileNames($this->directory.'/billing-b.zip'))->toBe([
            'billing-fed321cba098/composer.json',
            'billing-fed321cba098/src/Invoice.php',
        ]);
});

it('returns false without an output file when the full download fails', function () {
    $provider = new FixtureArchiveProvider($this->fixture, succeeds: false);
    $output = $this->directory.'/billing.zip';

    expect($provider->downloadArchive('abc123def4567890', $output, 'packages/billing'))->toBeFalse()
        ->and(file_exists($output))->toBeFalse()
        ->and($provider->cachedArchives())->toBe([]);
});

it('downloads the whole repository when no path is given', function () {
    $provider = new FixtureArchiveProvider($this->fixture);
    $output = $this->directory.'/copy.zip';

    expect($provider->downloadArchive('abc123def4567890', $output))->toBeTrue()
        ->and(sha1_file($output))->toBe(sha1_file($this->fixture))
        ->and($provider->downloadArchive('abc123def4567890', $this->directory.'/root.zip', '/'))->toBeTrue()
        ->and($provider->downloads)->toBe(2)
        ->and($provider->cachedArchives())->toBe([]);
});

it('returns false when the subdirectory is missing from the archive', function () {
    $provider = new FixtureArchiveProvider($this->fixture);
    $output = $this->directory.'/missing.zip';

    expect($provider->downloadArchive('abc123def4567890', $output, 'packages/missing'))->toBeFalse()
        ->and(file_exists($output))->toBeFalse();
});

it('removes the cached full archive when the provider is released', function () {
    $provider = new FixtureArchiveProvider($this->fixture);
    $provider->downloadArchive('abc123def4567890', $this->directory.'/billing.zip', 'packages/billing');

    $cached = array_values($provider->cachedArchives());

    expect($cached)->toHaveCount(1)
        ->and(file_exists($cached[0]))->toBeTrue();

    unset($provider);

    expect(file_exists($cached[0]))->toBeFalse();
});
