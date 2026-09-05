<?php

use App\Domains\Repository\Actions\FetchReadmeAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;

/**
 * @param  array<int, string>  $files
 */
function readmeProvider(array $files, string $path = ''): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('listDirectory')
        ->with('main', $path)
        ->andReturn(array_map(fn (string $name) => ['name' => $name, 'type' => 'file'], $files));

    return $provider;
}

it('returns the README found in the directory listing', function () {
    $provider = readmeProvider(['composer.json', 'README.md']);
    $provider->shouldReceive('getFileContent')->with('main', 'README.md')->once()->andReturn('# Hello');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('# Hello');
});

it('matches README filenames case-insensitively', function () {
    $provider = readmeProvider(['readme.md']);
    $provider->shouldReceive('getFileContent')->with('main', 'readme.md')->once()->andReturn('lowercase');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('lowercase');
});

it('prefers README.md over the other candidates', function () {
    $provider = readmeProvider(['README', 'README.markdown', 'README.md']);
    $provider->shouldReceive('getFileContent')->with('main', 'README.md')->once()->andReturn('markdown');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('markdown');
});

it('reads the README of a subdirectory', function () {
    $provider = readmeProvider(['README.md'], 'packages/billing');
    $provider->shouldReceive('getFileContent')->with('main', 'packages/billing/README.md')->once()->andReturn('# Billing');

    expect((new FetchReadmeAction)->handle($provider, 'main', 'packages/billing/'))->toBe('# Billing');
});

it('returns null when the directory holds no README', function () {
    $provider = readmeProvider(['composer.json', 'src']);
    $provider->shouldNotReceive('getFileContent');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();
});

it('rejects READMEs above the size cap', function () {
    $provider = readmeProvider(['README.md']);
    $provider->shouldReceive('getFileContent')->with('main', 'README.md')->andReturn(str_repeat('a', 513 * 1024));

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();
});

it('returns null when the provider throws', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('listDirectory')->once()->andThrow(new GitProviderException('rate limited'));
    $provider->shouldNotReceive('getFileContent');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();
});
