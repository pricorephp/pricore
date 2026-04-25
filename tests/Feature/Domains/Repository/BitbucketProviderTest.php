<?php

use App\Domains\Repository\Services\GitProviders\BitbucketProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

    $bitbucketProvider = new BitbucketProvider('', [
        'email' => 'user@example.com',
        'api_token' => 'token',
    ]);

    $repositories = $bitbucketProvider->getRepositories('acme');

    expect($repositories)->toHaveCount(150);
    expect($repositories[0]->fullName)->toBe('acme/repo-1');
    expect($repositories[149]->fullName)->toBe('acme/repo-150');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=2')
        && str_contains($request->url(), 'pagelen=100'));
});
