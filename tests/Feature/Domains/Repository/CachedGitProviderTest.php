<?php

use App\Domains\Repository\Services\GitProviders\CachedGitProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function runFixtureGit(string $directory, string ...$arguments): string
{
    $configuration = [
        '-c', 'user.name=Pricore',
        '-c', 'user.email=pricore@example.com',
        '-c', 'commit.gpgsign=false',
        '-c', 'init.defaultBranch=main',
    ];

    return trim(Process::path($directory)
        ->run(array_merge(['git'], $configuration, $arguments))
        ->throw()
        ->output());
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-cached-git-'.bin2hex(random_bytes(6));
    $work = $this->directory.'/work';

    File::ensureDirectoryExists($work.'/packages/billing/src');
    File::ensureDirectoryExists($work.'/packages/crm');
    File::ensureDirectoryExists($work.'/docs');
    file_put_contents($work.'/composer.json', '{"name": "acme/monorepo"}');
    file_put_contents($work.'/packages/billing/composer.json', '{"name": "acme/billing"}');
    file_put_contents($work.'/packages/billing/src/Invoice.php', '<?php');
    file_put_contents($work.'/packages/crm/composer.json', '{"name": "acme/crm"}');
    file_put_contents($work.'/docs/index.md', '# Docs');

    runFixtureGit($work, 'init', '-q');
    runFixtureGit($work, 'add', '.');
    runFixtureGit($work, 'commit', '-q', '-m', 'init');
    $this->sha = runFixtureGit($work, 'rev-parse', 'HEAD');

    runFixtureGit($this->directory, 'clone', '-q', '--bare', $work, $this->directory.'/bare.git');

    $this->provider = new CachedGitProvider($this->directory.'/bare.git', 'git@example.test:acme/monorepo.git');
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('lists the repository root', function () {
    expect($this->provider->listDirectory($this->sha, ''))->toBe([
        ['name' => 'composer.json', 'type' => 'file'],
        ['name' => 'docs', 'type' => 'dir'],
        ['name' => 'packages', 'type' => 'dir'],
    ]);
});

it('lists a subdirectory', function () {
    expect($this->provider->listDirectory($this->sha, 'packages/'))->toBe([
        ['name' => 'billing', 'type' => 'dir'],
        ['name' => 'crm', 'type' => 'dir'],
    ]);
});

it('returns an empty list for a missing directory or a file path', function () {
    expect($this->provider->listDirectory($this->sha, 'missing'))->toBe([])
        ->and($this->provider->listDirectory($this->sha, 'composer.json'))->toBe([])
        ->and($this->provider->listDirectory('--output=x', ''))->toBe([]);
});

it('reads a file inside a subdirectory', function () {
    expect($this->provider->getFileContent($this->sha, 'packages/billing/composer.json'))
        ->toBe('{"name": "acme/billing"}');
});

it('archives a subdirectory re-rooted under a prefix', function () {
    $output = $this->directory.'/billing.zip';
    $prefix = 'billing-'.substr($this->sha, 0, 12);

    expect($this->provider->downloadArchive($this->sha, $output, 'packages/billing'))->toBeTrue()
        ->and(testZipFileNames($output))->toBe([
            "{$prefix}/composer.json",
            "{$prefix}/src/Invoice.php",
        ]);
});

it('archives the whole repository when no path is given', function () {
    $output = $this->directory.'/full.zip';

    expect($this->provider->downloadArchive($this->sha, $output))->toBeTrue()
        ->and(testZipFileNames($output))->toContain('composer.json', 'packages/crm/composer.json');
});

it('returns false for a missing subdirectory', function () {
    expect($this->provider->downloadArchive($this->sha, $this->directory.'/missing.zip', 'missing'))->toBeFalse();
});
