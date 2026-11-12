<?php

use App\Domains\Repository\Exceptions\UnsafeGitRemoteException;
use App\Domains\Repository\Services\GitRemotes\GitRemotePolicy;
use App\Domains\Repository\Services\GitRemotes\HttpGitRemoteProbe;
use App\Domains\Repository\Services\GitRemotes\ResolvedGitRemote;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses()->group('security', 'repositories');

it('accepts remotes on a public address', function (string $url, string $expectedUrl) {
    expect(app(GitRemotePolicy::class)->check($url)->url)->toBe($expectedUrl);
})->with([
    'https' => ['https://git.example.com/acme/widgets.git', 'https://git.example.com/acme/widgets.git'],
    'http with a port' => ['http://git.example.com:8080/acme/widgets.git', 'http://git.example.com:8080/acme/widgets.git'],
    'ssh scheme' => ['ssh://git@git.example.com:2222/acme/widgets.git', 'ssh://git@git.example.com:2222/acme/widgets.git'],
    'scp shorthand' => ['git@git.example.com:acme/widgets.git', 'git@git.example.com:acme/widgets.git'],
    'git scheme' => ['git://git.example.com/acme/widgets.git', 'git://93.184.216.34/acme/widgets.git'],
    'public address' => ['https://93.184.216.34/acme/widgets.git', 'https://93.184.216.34/acme/widgets.git'],
    'mixed case and trailing dot' => ['HTTPS://Git.Example.COM./acme/widgets.git', 'https://git.example.com/acme/widgets.git'],
]);

it('rejects remotes on a non-public address', function (string $url) {
    app(GitRemotePolicy::class)->check($url);
})->with([
    'loopback' => 'http://127.0.0.1:8080/',
    'private' => 'https://10.0.0.5/acme/widgets.git',
    'link local' => 'http://169.254.169.254/latest/meta-data/',
    'carrier-grade NAT' => 'http://100.64.0.1/',
    'git scheme' => 'git://10.0.0.5/acme/widgets.git',
    'ssh scheme' => 'ssh://git@192.168.1.1:2222/acme/widgets.git',
    'scp shorthand' => 'git@192.168.1.1:acme/widgets.git',
    'IPv6 loopback' => 'http://[0:0:0:0:0:0:0:1]/',
    'IPv6 unique local' => 'http://[fd00:0:0:0:0:0:0:1]/',
    'IPv4-mapped loopback' => 'http://[0:0:0:0:0:ffff:7f00:1]/',
])->throws(UnsafeGitRemoteException::class, 'is not reachable on a public address');

it('rejects IPv4 addresses in other notations', function (string $url) {
    app(GitRemotePolicy::class)->check($url);
})->with([
    'shortened' => 'http://127.1/',
    'decimal' => 'http://2130706433/',
    'hexadecimal' => 'http://0x7f.0.0.1/',
    'octal' => 'http://0177.0.0.01/',
])->throws(UnsafeGitRemoteException::class, 'invalid hostname');

it('rejects hosts that do not resolve or have any non-public answer', function (array $addresses) {
    $this->hostResolver->set('git.example.com', $addresses);

    app(GitRemotePolicy::class)->check('https://git.example.com/acme/widgets.git');
})->with([
    'no answers' => [[]],
    'private answer' => [['10.0.0.10']],
    'mixed answers' => [['93.184.216.34', '10.0.0.10']],
    'IPv4-mapped private answer' => [['::ffff:10.0.0.10']],
])->throws(UnsafeGitRemoteException::class, 'Repository host git.example.com is not reachable on a public address');

it('takes the host from behind the last at sign', function () {
    $gitRemotePolicy = app(GitRemotePolicy::class);

    expect($gitRemotePolicy->check('https://dev:hun@ter2@git.example.com/widgets.git')->url)
        ->toBe('https://dev:hun%40ter2@git.example.com/widgets.git')
        ->and(fn () => $gitRemotePolicy->check('https://git.example.com@127.0.0.1/widgets.git'))
        ->toThrow(UnsafeGitRemoteException::class);
});

it('rejects URLs a transport could read differently', function (string $url) {
    app(GitRemotePolicy::class)->check($url);
})->with([
    'backslash' => 'https://git.example.com\\@127.0.0.1/widgets.git',
    'whitespace' => "https://git.example.com/widgets.git\n127.0.0.1",
    'empty port' => 'https://git.example.com:/widgets.git',
    'ext transport' => 'ext::sh -c id',
    'local path' => '/etc/passwd',
])->throws(UnsafeGitRemoteException::class);

it('allows a private host that is explicitly configured', function (string $allowedHost, string $url) {
    config()->set('pricore.git.allowed_private_hosts', [$allowedHost]);
    $this->hostResolver->set('git.internal', ['10.0.0.10']);

    $gitRemote = app(GitRemotePolicy::class)->check($url);

    expect($gitRemote->url)->toBe($url)
        ->and($gitRemote->addresses)->toBe([]);
})->with([
    'hostname' => ['GIT.INTERNAL.', 'ssh://git@git.internal/acme/widgets.git'],
    'address' => ['10.20.30.40', 'https://10.20.30.40/acme/widgets.git'],
    'scp shorthand' => ['git.internal', 'git@git.internal:acme/widgets.git'],
    'git scheme' => ['git.internal', 'git://git.internal/acme/widgets.git'],
]);

it('holds git to the addresses that were checked', function () {
    $this->hostResolver->set('git.example.com', ['2606:2800:220:1::1', '93.184.216.34']);
    $gitRemotePolicy = app(GitRemotePolicy::class);

    expect($gitRemotePolicy->check('https://git.example.com/widgets.git')->gitOptions())->toBe([
        '-c', 'http.followRedirects=false',
        '-c', 'http.curloptResolve=git.example.com:443:[2606:2800:220:1::1],93.184.216.34',
    ])
        ->and($gitRemotePolicy->check('http://git.example.com:8080/widgets.git')->gitOptions())
        ->toContain('http.curloptResolve=git.example.com:8080:[2606:2800:220:1::1],93.184.216.34')
        ->and($gitRemotePolicy->check('git@git.example.com:acme/widgets.git')->sshOptions())
        ->toBe(" -o 'HostName=93.184.216.34' -o 'HostKeyAlias=git.example.com'")
        ->and($gitRemotePolicy->check('ssh://git@git.example.com:2222/widgets.git')->sshOptions())
        ->toBe(" -o 'HostName=93.184.216.34' -o 'HostKeyAlias=[git.example.com]:2222'")
        ->and($gitRemotePolicy->check('ssh://git@git.example.com/widgets.git')->gitOptions())->toBe([]);
});

it('turns redirects off for an allowed private host without pinning it', function () {
    config()->set('pricore.git.allowed_private_hosts', ['git.internal']);

    $gitRemote = app(GitRemotePolicy::class)->check('https://git.internal/widgets.git');

    expect($gitRemote->gitOptions())->toBe(['-c', 'http.followRedirects=false'])
        ->and(app(GitRemotePolicy::class)->check('git@git.internal:acme/widgets.git')->sshOptions())->toBe('');
});

it('uses the address a remote redirects to', function () {
    $this->gitRemoteProbe->redirect(
        'https://git.example.com/acme/widgets/info/refs?service=git-upload-pack',
        '/acme/widgets.git/info/refs?service=git-upload-pack',
    );

    $gitRemote = app(GitRemotePolicy::class)->resolve('https://dev:hunter2@git.example.com/acme/widgets');

    expect($gitRemote->url)->toBe('https://dev:hunter2@git.example.com/acme/widgets.git/')
        ->and($this->gitRemoteProbe->probedUrls)->toBe([
            'https://git.example.com/acme/widgets/info/refs?service=git-upload-pack',
            'https://git.example.com/acme/widgets.git/info/refs?service=git-upload-pack',
        ]);
});

it('leaves credentials behind when a remote redirects to another origin', function () {
    $this->gitRemoteProbe->redirect(
        'http://git.example.com/widgets.git/info/refs?service=git-upload-pack',
        'https://code.example.org/widgets.git/info/refs?service=git-upload-pack',
    );

    expect(app(GitRemotePolicy::class)->resolve('http://dev:hunter2@git.example.com/widgets.git')->url)
        ->toBe('https://code.example.org/widgets.git/');
});

it('rejects a redirect to a non-public address', function (string $location) {
    $this->hostResolver->set('internal.example.com', ['10.0.0.10']);
    $this->gitRemoteProbe->redirect('https://git.example.com/widgets.git/info/refs?service=git-upload-pack', $location);

    app(GitRemotePolicy::class)->resolve('https://git.example.com/widgets.git');
})->with([
    'address' => 'http://169.254.169.254/latest/meta-data/?x=/info/refs?service=git-upload-pack',
    'hostname' => 'https://internal.example.com/widgets.git/info/refs?service=git-upload-pack',
])->throws(UnsafeGitRemoteException::class, 'is not reachable on a public address');

it('rejects a redirect that is not for the request git made', function (string $location) {
    $this->gitRemoteProbe->redirect('https://git.example.com/widgets.git/info/refs?service=git-upload-pack', $location);

    app(GitRemotePolicy::class)->resolve('https://git.example.com/widgets.git');
})->with([
    'other path' => 'https://code.example.org/login',
    'other scheme' => 'ftp://code.example.org/widgets.git/info/refs?service=git-upload-pack',
])->throws(UnsafeGitRemoteException::class, 'redirects to an address that is not a Git repository');

it('gives up on a redirect loop', function () {
    $this->gitRemoteProbe->redirect(
        'https://git.example.com/widgets.git/info/refs?service=git-upload-pack',
        'https://git.example.com/widgets.git/info/refs?service=git-upload-pack',
    );

    app(GitRemotePolicy::class)->resolve('https://git.example.com/widgets.git');
})->throws(UnsafeGitRemoteException::class, 'redirects too many times');

it('does not probe remotes that cannot redirect', function (string $url) {
    app(GitRemotePolicy::class)->resolve($url);

    expect($this->gitRemoteProbe->probedUrls)->toBe([]);
})->with([
    'ssh scheme' => 'ssh://git@git.example.com/widgets.git',
    'scp shorthand' => 'git@git.example.com:acme/widgets.git',
    'git scheme' => 'git://git.example.com/widgets.git',
]);

it('reads where a remote redirects to without following it or sending credentials', function () {
    Http::fake(['git.example.com/*' => Http::response('', 301, ['Location' => 'https://git.example.com/widgets.git/info/refs?service=git-upload-pack'])]);

    $gitRemote = new ResolvedGitRemote('https://dev:hunter2@git.example.com/widgets', 'https', 'git.example.com', addresses: ['93.184.216.34']);

    expect((new HttpGitRemoteProbe)->redirectLocation($gitRemote))
        ->toBe('https://git.example.com/widgets.git/info/refs?service=git-upload-pack');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://git.example.com/widgets/info/refs?service=git-upload-pack'
        && ! $request->hasHeader('Authorization'));
});

it('reports no redirect when a remote answers or cannot be reached', function (Closure $fake) {
    Http::fake($fake);

    $gitRemote = new ResolvedGitRemote('https://git.example.com/widgets.git', 'https', 'git.example.com', addresses: ['93.184.216.34']);

    expect((new HttpGitRemoteProbe)->redirectLocation($gitRemote))->toBeNull();
})->with([
    'answers' => [fn () => Http::response('001e# service=git-upload-pack', 200)],
    'asks for credentials' => [fn () => Http::response('', 401)],
    'redirect without a location' => [fn () => Http::response('', 302)],
    'unreachable' => [fn () => throw new ConnectionException('Connection refused')],
]);
