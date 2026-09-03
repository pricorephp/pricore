<?php

use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Domains\Mirror\Services\Http\MirrorUrlPolicy;

uses()->group('security', 'mirrors');

it('accepts public HTTP and HTTPS mirror URLs', function (string $url) {
    $resolved = app(MirrorUrlPolicy::class)->resolveMirrorOrigin($url);

    expect($resolved->url)->toBe($url);
})->with([
    'https' => 'https://registry.example.com/packages.json',
    'http' => 'http://registry.example.com/packages.json',
]);

it('rejects non-http URLs and embedded credentials', function (string $url) {
    app(MirrorUrlPolicy::class)->resolveMirrorOrigin($url);
})->with([
    'file URL' => 'file:///etc/passwd',
    'FTP URL' => 'ftp://registry.example.com/packages.json',
    'relative URL' => '/packages.json',
    'embedded credentials' => 'https://user:secret@registry.example.com/packages.json',
])->throws(UnsafeMirrorUrlException::class);

it('rejects non-public IP address ranges', function (string $address) {
    app(MirrorUrlPolicy::class)->resolveMirrorOrigin("http://[{$address}]/packages.json");
})->with([
    'IPv6 loopback' => '::1',
    'IPv6 unique local' => 'fd00::1',
    'IPv6 link local' => 'fe80::1',
    'IPv6 documentation' => '2001:db8::1',
    'IPv4-mapped loopback' => '::ffff:127.0.0.1',
])->throws(UnsafeMirrorUrlException::class);

it('rejects non-public IPv4 address ranges', function (string $address) {
    app(MirrorUrlPolicy::class)->resolveMirrorOrigin("http://{$address}/packages.json");
})->with([
    'unspecified' => '0.0.0.1',
    'private' => '10.0.0.1',
    'carrier-grade NAT' => '100.64.0.1',
    'loopback' => '127.0.0.1',
    'link local' => '169.254.169.254',
    'documentation' => '192.0.2.1',
    'benchmark' => '198.18.0.1',
    'multicast' => '224.0.0.1',
])->throws(UnsafeMirrorUrlException::class);

it('rejects unresolved hosts and hosts with any private result', function (array $addresses) {
    $this->hostResolver->set('registry.example.com', $addresses);

    app(MirrorUrlPolicy::class)->resolveMirrorOrigin('https://registry.example.com');
})->with([
    'no answers' => [[]],
    'private answer' => [['10.0.0.10']],
    'mixed answers' => [['93.184.216.34', '10.0.0.10']],
])->throws(UnsafeMirrorUrlException::class);

it('allows an explicitly configured private mirror origin and same-origin targets', function () {
    config()->set('pricore.mirrors.allowed_private_hosts', ['REGISTRY.INTERNAL.']);
    $this->hostResolver->set('registry.internal', ['10.0.0.10']);

    $policy = app(MirrorUrlPolicy::class);

    expect($policy->resolveMirrorOrigin('https://registry.internal')->addresses)->toBe(['10.0.0.10'])
        ->and($policy->resolveForMirror(
            'https://registry.internal/p2/acme/package.json',
            'https://registry.internal',
        )->addresses)->toBe(['10.0.0.10']);
});

it('rejects private cross-origin targets even when that host is allowlisted', function () {
    config()->set('pricore.mirrors.allowed_private_hosts', ['registry.internal']);
    $this->hostResolver->set('registry.internal', ['10.0.0.10']);

    app(MirrorUrlPolicy::class)->resolveForMirror(
        'https://registry.internal/secret',
        'https://attacker.example.com',
    );
})->throws(UnsafeMirrorUrlException::class);

it('normalizes IPv4-mapped public addresses before pinning', function () {
    $this->hostResolver->set('registry.example.com', ['::ffff:93.184.216.34']);

    $resolved = app(MirrorUrlPolicy::class)->resolveMirrorOrigin('https://registry.example.com');

    expect($resolved->addresses)->toBe(['93.184.216.34']);
});
