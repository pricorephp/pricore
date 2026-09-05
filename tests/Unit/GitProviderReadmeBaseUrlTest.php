<?php

use App\Domains\Repository\Contracts\Enums\GitProvider;

it('appends the package directory to the README link bases', function () {
    expect(GitProvider::GitHub->rawFileBaseUrl('acme/mono', 'abc', null, 'packages/billing'))
        ->toBe('https://raw.githubusercontent.com/acme/mono/abc/packages/billing/')
        ->and(GitProvider::GitHub->blobBaseUrl('acme/mono', 'abc', null, '/packages/billing/'))
        ->toBe('https://github.com/acme/mono/blob/abc/packages/billing/')
        ->and(GitProvider::GitLab->rawFileBaseUrl('acme/mono', 'abc', 'https://git.example.test', 'packages/billing'))
        ->toBe('https://git.example.test/acme/mono/-/raw/abc/packages/billing/')
        ->and(GitProvider::Bitbucket->blobBaseUrl('acme/mono', 'abc', null, 'packages/billing'))
        ->toBe('https://bitbucket.org/acme/mono/src/abc/packages/billing/')
        ->and(GitProvider::Git->rawFileBaseUrl('git@example.test:acme/mono.git', 'abc', null, 'packages/billing'))
        ->toBeNull();
});

it('keeps the repository root as the base without a directory', function () {
    expect(GitProvider::GitHub->rawFileBaseUrl('acme/mono', 'abc'))
        ->toBe('https://raw.githubusercontent.com/acme/mono/abc/')
        ->and(GitProvider::GitHub->blobBaseUrl('acme/mono', 'abc', null, null))
        ->toBe('https://github.com/acme/mono/blob/abc/');
});
