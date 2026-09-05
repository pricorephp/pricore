<?php

use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function githubProvider(): GitHubProvider
{
    return new GitHubProvider('acme/monorepo', ['token' => 'token']);
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-github-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('lists directory entries with their types', function () {
    Http::fake([
        'api.github.com/repos/acme/monorepo/contents/packages*' => Http::response([
            ['name' => 'billing', 'type' => 'dir'],
            ['name' => 'crm', 'type' => 'dir'],
            ['name' => 'README.md', 'type' => 'file'],
            ['name' => 'link', 'type' => 'symlink'],
        ]),
    ]);

    expect(githubProvider()->listDirectory('v1.0.0', 'packages/'))->toBe([
        ['name' => 'billing', 'type' => 'dir'],
        ['name' => 'crm', 'type' => 'dir'],
        ['name' => 'README.md', 'type' => 'file'],
        ['name' => 'link', 'type' => 'file'],
    ]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/contents/packages?ref=v1.0.0'));
});

it('lists the repository root for an empty path', function () {
    Http::fake([
        'api.github.com/repos/acme/monorepo/contents/*' => Http::response([
            ['name' => 'composer.json', 'type' => 'file'],
        ]),
    ]);

    expect(githubProvider()->listDirectory('main', ''))->toBe([
        ['name' => 'composer.json', 'type' => 'file'],
    ]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/contents/?ref=main'));
});

it('returns an empty list for a missing directory', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    expect(githubProvider()->listDirectory('main', 'packages'))->toBe([]);
});

it('returns an empty list when the path is a file', function () {
    Http::fake([
        'api.github.com/*' => Http::response([
            'name' => 'composer.json',
            'type' => 'file',
            'content' => base64_encode('{}'),
        ]),
    ]);

    expect(githubProvider()->listDirectory('main', 'composer.json'))->toBe([]);
});

it('throws for other API failures when listing a directory', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Forbidden'], 403)]);

    expect(fn () => githubProvider()->listDirectory('main', 'packages'))
        ->toThrow(GitProviderException::class, 'Failed to list directory on GitHub');
});

it('returns null for a missing file instead of failing', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    expect(githubProvider()->getFileContent('main', 'composer.json'))->toBeNull();
});

it('cuts a subdirectory archive from the zipball and reuses the download', function () {
    $fixture = $this->directory.'/full.zip';

    createTestZip($fixture, [
        'acme-monorepo-abc1234/composer.json' => 'root',
        'acme-monorepo-abc1234/packages/billing/composer.json' => 'billing',
        'acme-monorepo-abc1234/packages/crm/composer.json' => 'crm',
    ]);

    Http::fake([
        'api.github.com/repos/acme/monorepo/zipball/*' => Http::response((string) file_get_contents($fixture)),
    ]);

    $provider = githubProvider();
    $ref = 'abc123def4567890abcdef';

    expect($provider->downloadArchive($ref, $this->directory.'/billing.zip', 'packages/billing'))->toBeTrue()
        ->and(testZipFileNames($this->directory.'/billing.zip'))->toBe(['billing-abc123def456/composer.json'])
        ->and($provider->downloadArchive($ref, $this->directory.'/crm.zip', 'packages/crm'))->toBeTrue()
        ->and(testZipFileNames($this->directory.'/crm.zip'))->toBe(['crm-abc123def456/composer.json']);

    Http::assertSentCount(1);
});

it('downloads the whole zipball when no path is given', function () {
    Http::fake(['api.github.com/repos/acme/monorepo/zipball/abc123*' => Http::response('zip-bytes')]);

    $output = $this->directory.'/full.zip';

    expect(githubProvider()->downloadArchive('abc123', $output))->toBeTrue()
        ->and(file_get_contents($output))->toBe('zip-bytes');
});
