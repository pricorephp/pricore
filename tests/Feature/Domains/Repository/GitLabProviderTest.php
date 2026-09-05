<?php

use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GitLabProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function gitlabProvider(): GitLabProvider
{
    return new GitLabProvider('acme/monorepo', ['token' => 'token']);
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-gitlab-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('lists directory entries across pages', function () {
    $page1 = array_map(fn (int $i) => ['name' => "package-{$i}", 'type' => 'tree'], range(1, 100));
    $page2 = [['name' => 'README.md', 'type' => 'blob']];

    Http::fake([
        'gitlab.com/api/v4/projects/*/repository/tree*&page=2*' => Http::response($page2),
        'gitlab.com/api/v4/projects/*/repository/tree*' => Http::response($page1),
    ]);

    $entries = gitlabProvider()->listDirectory('v1.0.0', 'packages');

    expect($entries)->toHaveCount(101)
        ->and($entries[0])->toBe(['name' => 'package-1', 'type' => 'dir'])
        ->and($entries[100])->toBe(['name' => 'README.md', 'type' => 'file']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'path=packages')
        && str_contains($request->url(), 'ref=v1.0.0'));
});

it('omits the path parameter for the repository root', function () {
    Http::fake(['gitlab.com/api/v4/projects/*/repository/tree*' => Http::response([])]);

    expect(gitlabProvider()->listDirectory('main', ''))->toBe([]);

    Http::assertSent(fn (Request $request) => ! str_contains($request->url(), 'path='));
});

it('returns an empty list for a missing directory', function () {
    Http::fake(['gitlab.com/*' => Http::response(['message' => '404 Tree Not Found'], 404)]);

    expect(gitlabProvider()->listDirectory('main', 'packages'))->toBe([]);
});

it('throws for other API failures when listing a directory', function () {
    Http::fake(['gitlab.com/*' => Http::response(['message' => '403 Forbidden'], 403)]);

    expect(fn () => gitlabProvider()->listDirectory('main', 'packages'))
        ->toThrow(GitProviderException::class, 'Failed to list directory on GitLab');
});

it('returns null for a missing file instead of failing', function () {
    Http::fake(['gitlab.com/*' => Http::response(['message' => '404 File Not Found'], 404)]);

    expect(gitlabProvider()->getFileContent('main', 'composer.json'))->toBeNull();
});

it('cuts a subdirectory archive from the full archive download', function () {
    $fixture = $this->directory.'/full.zip';

    createTestZip($fixture, [
        'monorepo-abc123def4567890-abc123def4567890/composer.json' => 'root',
        'monorepo-abc123def4567890-abc123def4567890/packages/billing/composer.json' => 'billing',
    ]);

    Http::fake([
        'gitlab.com/api/v4/projects/*/repository/archive.zip*' => Http::response((string) file_get_contents($fixture)),
    ]);

    $output = $this->directory.'/billing.zip';

    expect(gitlabProvider()->downloadArchive('abc123def4567890', $output, 'packages/billing'))->toBeTrue()
        ->and(testZipFileNames($output))->toBe(['billing-abc123def456/composer.json']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sha=abc123def4567890'));
});
