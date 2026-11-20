<?php

use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Exceptions\UnsafeGitRemoteException;
use App\Domains\Repository\Services\GitProviders\GenericGitProvider;
use App\Models\Repository;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

uses()->group('security', 'repositories');

beforeEach(function () {
    Process::fake(['*' => Process::result(output: "0000000000000000000000000000000000000001\trefs/heads/main\n")]);
});

/**
 * A row can predate request validation, and a hostname can start resolving
 * somewhere else after the repository was added.
 */
it('does not run git against a stored repository on a non-public address', function (string $url) {
    $this->hostResolver->set('git.internal', ['10.0.0.10']);
    $genericGitProvider = new GenericGitProvider($url, []);

    expect(fn () => $genericGitProvider->ensureRepositoryAccess())
        ->toThrow(UnsafeGitRemoteException::class, 'is not reachable on a public address')
        ->and(fn () => $genericGitProvider->getTags())->toThrow(UnsafeGitRemoteException::class)
        ->and(fn () => $genericGitProvider->getBranches())->toThrow(UnsafeGitRemoteException::class)
        ->and($genericGitProvider->getFileContent('main', 'composer.json'))->toBeNull();

    Process::assertNothingRan();
})->with([
    'loopback' => 'http://127.0.0.1:8080/',
    'private over git' => 'git://10.0.0.5/acme/widgets.git',
    'private over ssh' => 'ssh://git@192.168.1.1/acme/widgets.git',
    'name resolving to a private address' => 'https://git.internal/acme/widgets.git',
]);

it('does not clone a stored repository on a non-public address', function () {
    $repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'http://169.254.169.254/latest/meta-data/',
    ]);

    expect(fn () => app(CreateGitCloneAction::class)->handle($repository))
        ->toThrow(UnsafeGitRemoteException::class);

    Process::assertNothingRan();
});

it('does not run git when the remote redirects to a non-public address', function () {
    $this->gitRemoteProbe->redirect(
        'https://git.example.com/widgets.git/info/refs?service=git-upload-pack',
        'http://127.0.0.1:8080/widgets.git/info/refs?service=git-upload-pack',
    );

    expect(fn () => (new GenericGitProvider('https://git.example.com/widgets.git', []))->ensureRepositoryAccess())
        ->toThrow(UnsafeGitRemoteException::class);

    Process::assertNothingRan();
});

it('runs git against the checked addresses with redirects turned off', function () {
    (new GenericGitProvider('https://git.example.com/widgets.git', []))->getBranches();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'git',
        '-c', 'http.followRedirects=false',
        '-c', 'http.curloptResolve=git.example.com:443:93.184.216.34',
        'ls-remote', '--heads', '--', 'https://git.example.com/widgets.git',
    ]);
});

it('points git at the address a remote redirects to', function () {
    $this->gitRemoteProbe->redirect(
        'https://git.example.com/acme/widgets/info/refs?service=git-upload-pack',
        'https://git.example.com/acme/widgets.git/info/refs?service=git-upload-pack',
    );

    (new GenericGitProvider('https://git.example.com/acme/widgets', []))->getBranches();

    Process::assertRan(fn (PendingProcess $process) => in_array('https://git.example.com/acme/widgets.git/', (array) $process->command, true));
});

it('holds ssh to the checked address', function () {
    (new GenericGitProvider('git@git.example.com:acme/widgets.git', ['ssh_key' => 'private-key']))->getBranches();

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['git', 'ls-remote', '--heads', '--', 'git@git.example.com:acme/widgets.git']
        && str_ends_with($process->environment['GIT_SSH_COMMAND'], " -o 'HostName=93.184.216.34' -o 'HostKeyAlias=git.example.com'"));
});

it('connects to the checked address of a git server under its hostname', function () {
    (new GenericGitProvider('git://git.example.com/widgets.git', []))->getBranches();

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['git', 'ls-remote', '--heads', '--', 'git://93.184.216.34/widgets.git']
        && $process->environment['GIT_OVERRIDE_VIRTUAL_HOST'] === 'git.example.com');
});

it('clones from the checked addresses with redirects turned off', function () {
    $repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'https://git.example.com/widgets.git',
    ]);

    app(CreateGitCloneAction::class)->handle($repository);

    Process::assertRan(fn (PendingProcess $process) => array_slice((array) $process->command, 0, 7) === [
        'git',
        '-c', 'http.followRedirects=false',
        '-c', 'http.curloptResolve=git.example.com:443:93.184.216.34',
        'clone', '--bare',
    ]);
});
