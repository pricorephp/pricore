<?php

use App\Domains\Repository\Actions\FetchReadmeAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;

it('returns the contents of the first README candidate found', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')
        ->with('main', 'README.md')
        ->andReturn('# Hello');
    $provider->shouldNotReceive('getFileContent')->with('main', 'readme.md');

    $result = (new FetchReadmeAction)->handle($provider, 'main');

    expect($result)->toBe('# Hello');
});

it('falls back to alternate filenames', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')->with('main', 'README.md')->andReturn(null);
    $provider->shouldReceive('getFileContent')->with('main', 'readme.md')->andReturn('lowercase');
    $provider->shouldReceive('getFileContent')->byDefault()->andReturn(null);

    $result = (new FetchReadmeAction)->handle($provider, 'main');

    expect($result)->toBe('lowercase');
});

it('returns null when no candidate filename exists', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getFileContent')->andReturn(null);

    $result = (new FetchReadmeAction)->handle($provider, 'main');

    expect($result)->toBeNull();
});

it('rejects READMEs above the size cap', function () {
    $oversized = str_repeat('a', 513 * 1024);

    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('getFileContent')->with('main', 'README.md')->andReturn($oversized);

    $result = (new FetchReadmeAction)->handle($provider, 'main');

    expect($result)->toBeNull();
});

it('returns null and stops probing when the provider throws', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('getFileContent')
        ->with('main', 'README.md')
        ->once()
        ->andThrow(new GitProviderException('rate limited'));
    $provider->shouldNotReceive('getFileContent')->with('main', 'readme.md');

    $result = (new FetchReadmeAction)->handle($provider, 'main');

    expect($result)->toBeNull();
});
