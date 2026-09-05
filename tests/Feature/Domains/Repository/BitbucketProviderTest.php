<?php

use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\BitbucketProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function bitbucketProvider(): BitbucketProvider
{
    return new BitbucketProvider('', [
        'email' => 'user@example.com',
        'api_token' => 'token',
    ]);
}

it('returns no owners because Bitbucket sunset cross-workspace enumeration', function () {
    Http::fake();

    expect(bitbucketProvider()->getOwners())->toBe([]);

    Http::assertNothingSent();
});

it('throws when called without a workspace because cross-workspace listing was sunset', function () {
    expect(fn () => bitbucketProvider()->getRepositories())
        ->toThrow(GitProviderException::class, 'Bitbucket requires a workspace');

    expect(fn () => bitbucketProvider()->getRepositories(''))
        ->toThrow(GitProviderException::class, 'Bitbucket requires a workspace');
});

it('paginates through all workspace repositories without dropping the next-page query', function () {
    $page1 = [
        'values' => array_map(fn (int $i) => [
            'slug' => "repo-{$i}",
            'full_name' => "acme/repo-{$i}",
            'is_private' => true,
            'description' => null,
        ], range(1, 100)),
        'next' => 'https://api.bitbucket.org/2.0/repositories/acme?page=2&pagelen=100',
    ];

    $page2 = [
        'values' => array_map(fn (int $i) => [
            'slug' => "repo-{$i}",
            'full_name' => "acme/repo-{$i}",
            'is_private' => true,
            'description' => null,
        ], range(101, 150)),
    ];

    Http::fake([
        'api.bitbucket.org/2.0/repositories/acme?page=2*' => Http::response($page2, 200),
        'api.bitbucket.org/2.0/repositories/acme*' => Http::response($page1, 200),
    ]);

    $repositories = bitbucketProvider()->getRepositories('acme');

    expect($repositories)->toHaveCount(150);
    expect($repositories[0]->fullName)->toBe('acme/repo-1');
    expect($repositories[149]->fullName)->toBe('acme/repo-150');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=2')
        && str_contains($request->url(), 'pagelen=100'));
});

it('translates 403 scope failures on the workspace repos endpoint into a helpful message', function () {
    Http::fake([
        'api.bitbucket.org/2.0/repositories/acme*' => Http::response([
            'error' => ['detail' => ['required' => ['read:repository:bitbucket']]],
        ], 403),
    ]);

    expect(fn () => bitbucketProvider()->getRepositories('acme'))
        ->toThrow(GitProviderException::class, 'missing required scopes');
});

function bitbucketMonorepoProvider(): BitbucketProvider
{
    return new BitbucketProvider('acme/monorepo', [
        'email' => 'user@example.com',
        'api_token' => 'token',
    ]);
}

it('lists directory entries and follows pagination', function () {
    Http::fake([
        'api.bitbucket.org/2.0/repositories/acme/monorepo/src/v1.0.0/packages/?page=2*' => Http::response([
            'values' => [
                ['path' => 'packages/crm', 'type' => 'commit_directory'],
            ],
        ]),
        'api.bitbucket.org/2.0/repositories/acme/monorepo/src/v1.0.0/packages/*' => Http::response([
            'values' => [
                ['path' => 'packages/billing', 'type' => 'commit_directory'],
                ['path' => 'packages/README.md', 'type' => 'commit_file'],
            ],
            'next' => 'https://api.bitbucket.org/2.0/repositories/acme/monorepo/src/v1.0.0/packages/?page=2&pagelen=100',
        ]),
    ]);

    expect(bitbucketMonorepoProvider()->listDirectory('v1.0.0', 'packages'))->toBe([
        ['name' => 'billing', 'type' => 'dir'],
        ['name' => 'README.md', 'type' => 'file'],
        ['name' => 'crm', 'type' => 'dir'],
    ]);
});

it('lists the repository root for an empty path', function () {
    Http::fake([
        'api.bitbucket.org/2.0/repositories/acme/monorepo/src/main/?*' => Http::response([
            'values' => [['path' => 'composer.json', 'type' => 'commit_file']],
        ]),
    ]);

    expect(bitbucketMonorepoProvider()->listDirectory('main', ''))->toBe([
        ['name' => 'composer.json', 'type' => 'file'],
    ]);
});

it('returns an empty list for a missing directory', function () {
    Http::fake(['api.bitbucket.org/*' => Http::response(['error' => ['message' => 'Not found']], 404)]);

    expect(bitbucketMonorepoProvider()->listDirectory('main', 'packages'))->toBe([]);
});

it('returns an empty list when the path is a file', function () {
    Http::fake(['api.bitbucket.org/*' => Http::response('{"name": "acme/monorepo"}')]);

    expect(bitbucketMonorepoProvider()->listDirectory('main', 'composer.json'))->toBe([]);
});

it('cuts a subdirectory archive from the full archive download', function () {
    $directory = sys_get_temp_dir().'/pricore-bitbucket-'.bin2hex(random_bytes(6));
    mkdir($directory);

    try {
        $fixture = $directory.'/full.zip';

        createTestZip($fixture, [
            'acme-monorepo-abc123def456/composer.json' => 'root',
            'acme-monorepo-abc123def456/packages/billing/composer.json' => 'billing',
        ]);

        Http::fake([
            'bitbucket.org/acme/monorepo/get/*' => Http::response((string) file_get_contents($fixture)),
        ]);

        $output = $directory.'/billing.zip';

        expect(bitbucketMonorepoProvider()->downloadArchive('abc123def4567890', $output, 'packages/billing'))->toBeTrue()
            ->and(testZipFileNames($output))->toBe(['billing-abc123def456/composer.json']);
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
});
