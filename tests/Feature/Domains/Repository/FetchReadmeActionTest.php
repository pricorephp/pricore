<?php

use App\Domains\Repository\Actions\FetchReadmeAction;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;

/**
 * @param  array<string, string>  $files  file name => contents
 */
function readmeProvider(array $files, string $path = ''): GitProviderInterface
{
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('listDirectory')
        ->with('main', $path)
        ->andReturn(array_map(fn (string $name) => ['name' => $name, 'type' => 'file'], array_keys($files)));
    $provider->shouldReceive('getFileContent')
        ->andReturnUsing(fn (string $ref, string $file) => $files[$path === '' ? $file : substr($file, strlen($path) + 1)] ?? null);

    return $provider;
}

it('reads README.md without listing the directory', function () {
    $provider = readmeProvider(['composer.json' => '{}', 'README.md' => '# Hello']);

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('# Hello');

    $provider->shouldNotHaveReceived('listDirectory');
});

it('matches README filenames case-insensitively', function () {
    $provider = readmeProvider(['readme.md' => 'lowercase']);

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('lowercase');
});

it('falls back to the other candidates in order of preference', function () {
    $provider = readmeProvider(['README' => 'plain', 'README.markdown' => 'markdown']);

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBe('markdown');
});

it('reads the README of a subdirectory', function () {
    $provider = readmeProvider(['README.md' => '# Billing'], 'packages/billing');

    expect((new FetchReadmeAction)->handle($provider, 'main', 'packages/billing/'))->toBe('# Billing');

    $provider->shouldHaveReceived('getFileContent')->with('main', 'packages/billing/README.md');
});

it('returns null when the directory holds no README', function () {
    $provider = readmeProvider(['composer.json' => '{}', 'src' => '']);

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();

    $provider->shouldHaveReceived('getFileContent')->once();
});

it('rejects READMEs above the size cap', function () {
    $provider = readmeProvider(['README.md' => str_repeat('a', 513 * 1024)]);

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();
});

it('returns null when the provider throws', function () {
    $provider = Mockery::mock(GitProviderInterface::class);
    $provider->shouldReceive('getRepositoryIdentifier')->andReturn('vendor/pkg');
    $provider->shouldReceive('getFileContent')->once()->andThrow(new GitProviderException('rate limited'));
    $provider->shouldNotReceive('listDirectory');

    expect((new FetchReadmeAction)->handle($provider, 'main'))->toBeNull();
});
