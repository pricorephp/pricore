<?php

use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Models\Repository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function runCloneFixtureGit(string $directory, string ...$arguments): string
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
    $this->directory = sys_get_temp_dir().'/pricore-git-clone-'.bin2hex(random_bytes(6));
    $this->work = $this->directory.'/work';

    File::ensureDirectoryExists($this->work);
    file_put_contents($this->work.'/composer.json', '{"name": "acme/package", "version": "1"}');

    runCloneFixtureGit($this->work, 'init', '-q');
    runCloneFixtureGit($this->work, 'add', '.');
    runCloneFixtureGit($this->work, 'commit', '-q', '-m', 'init');
    runCloneFixtureGit($this->work, 'tag', 'v1.0.0');
    runCloneFixtureGit($this->work, 'branch', 'feature');

    $this->repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'https://git.example.test/acme/package.git',
    ]);

    // Stands in for a clone left behind by an earlier sync. The repository's address
    // resolves to the fixture, which identifiers cannot point at directly.
    $this->clonePath = storage_path("app/git-clones/{$this->repository->uuid}");
    runCloneFixtureGit($this->directory, 'clone', '-q', '--bare', $this->work, $this->clonePath);
    runCloneFixtureGit($this->clonePath, 'config', "url.{$this->work}.insteadOf", $this->repository->repo_identifier);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
    File::deleteDirectory($this->clonePath);
});

it('moves branches and adds tags when updating an existing clone', function () {
    file_put_contents($this->work.'/composer.json', '{"name": "acme/package", "version": "2"}');
    runCloneFixtureGit($this->work, 'commit', '-q', '-am', 'second');
    runCloneFixtureGit($this->work, 'tag', 'v2.0.0');
    runCloneFixtureGit($this->work, 'branch', 'release');
    $sha = runCloneFixtureGit($this->work, 'rev-parse', 'HEAD');

    expect(app(CreateGitCloneAction::class)->handle($this->repository))->toBe($this->clonePath);

    expect(runCloneFixtureGit($this->clonePath, 'rev-parse', 'refs/heads/main'))->toBe($sha)
        ->and(runCloneFixtureGit($this->clonePath, 'rev-parse', 'refs/heads/release'))->toBe($sha)
        ->and(runCloneFixtureGit($this->clonePath, 'rev-parse', 'refs/tags/v2.0.0^{commit}'))->toBe($sha)
        ->and(runCloneFixtureGit($this->clonePath, 'show', 'main:composer.json'))
        ->toBe('{"name": "acme/package", "version": "2"}');
});

it('prunes branches deleted upstream when updating an existing clone', function () {
    runCloneFixtureGit($this->work, 'branch', '-D', 'feature');

    app(CreateGitCloneAction::class)->handle($this->repository);

    expect(runCloneFixtureGit($this->clonePath, 'branch', '--list', 'feature'))->toBe('');
});

it('fetches from the repository address when a leftover clone has an outdated origin', function () {
    runCloneFixtureGit($this->clonePath, 'remote', 'set-url', 'origin', 'https://retired.example.test/acme/package.git');

    file_put_contents($this->work.'/composer.json', '{"name": "acme/package", "version": "2"}');
    runCloneFixtureGit($this->work, 'commit', '-q', '-am', 'second');
    $sha = runCloneFixtureGit($this->work, 'rev-parse', 'HEAD');

    app(CreateGitCloneAction::class)->handle($this->repository);

    expect(runCloneFixtureGit($this->clonePath, 'rev-parse', 'refs/heads/main'))->toBe($sha);
});

it('keeps URL credentials out of a failed clone', function () {
    $repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'https://dev:hunter2@git.example.test/acme/package.git',
    ]);

    Process::fake(['*' => Process::result(
        errorOutput: "fatal: unable to access 'https://dev:hunter2@git.example.test/acme/package.git/': 403\n",
        exitCode: 128,
    )]);

    try {
        app(CreateGitCloneAction::class)->handle($repository);
        $this->fail('Expected a GitProviderException');
    } catch (GitProviderException $e) {
        expect($e->getMessage())
            ->toContain("Failed to clone repository: fatal: unable to access 'https://***@git.example.test/acme/package.git/'")
            ->not->toContain('hunter2');
    }
});

it('keeps URL credentials out of a failed clone update', function () {
    $this->repository->update(['repo_identifier' => 'https://dev:hunter2@git.example.test/acme/package.git']);

    Process::fake(['*' => Process::result(
        errorOutput: "fatal: unable to access 'https://dev:hunter2@git.example.test/acme/package.git/': 403\n",
        exitCode: 128,
    )]);

    try {
        app(CreateGitCloneAction::class)->handle($this->repository);
        $this->fail('Expected a GitProviderException');
    } catch (GitProviderException $e) {
        expect($e->getMessage())
            ->toContain("Failed to update repository clone: fatal: unable to access 'https://***@git.example.test/acme/package.git/'")
            ->not->toContain('hunter2');
    }
});
