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
