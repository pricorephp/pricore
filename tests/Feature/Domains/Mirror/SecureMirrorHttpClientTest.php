<?php

use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Domains\Mirror\Services\RegistryClient\RegistryClientFactory;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $organization = Organization::factory()->create();
    $this->mirror = Mirror::factory()->withBasicAuth()->create([
        'organization_uuid' => $organization->uuid,
        'url' => 'https://registry.example.com',
        'auth_credentials' => [
            'username' => 'mirror-user',
            'password' => 'mirror-secret',
        ],
    ]);
});

it('disables automatic redirects and pins every resolved address', function () {
    $this->hostResolver->set('registry.example.com', [
        '93.184.216.34',
        '2606:4700:4700::1111',
    ]);
    $capturedOptions = null;

    Http::fake(function (Request $request, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return Http::response(['packages' => []]);
    });

    RegistryClientFactory::createHttpClient($this->mirror)
        ->get('https://registry.example.com/packages.json');

    expect($capturedOptions['allow_redirects'])->toBeFalse()
        ->and($capturedOptions['proxy'])->toBe('')
        ->and($capturedOptions['curl'][CURLOPT_RESOLVE])->toBe([
            'registry.example.com:443:93.184.216.34,[2606:4700:4700::1111]',
        ]);
});

it('revalidates redirect targets before sending another request', function () {
    Http::fakeSequence()
        ->push('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])
        ->push('internal secret');

    expect(fn () => RegistryClientFactory::createHttpClient($this->mirror)
        ->get('https://registry.example.com/packages.json'))
        ->toThrow(UnsafeMirrorUrlException::class);

    Http::assertSentCount(1);
});

it('follows public redirects and drops credentials across origins', function () {
    $sentRequests = [];

    Http::fake(function (Request $request) use (&$sentRequests) {
        $sentRequests[] = $request;

        return str_contains($request->url(), 'registry.example.com')
            ? Http::response('', 302, ['Location' => 'https://cdn.example.com/archive.zip'])
            : Http::response('archive');
    });

    RegistryClientFactory::createHttpClient($this->mirror)
        ->get('https://registry.example.com/archive.zip');

    expect($sentRequests)->toHaveCount(2)
        ->and($sentRequests[0]->hasHeader('Authorization'))->toBeTrue()
        ->and($sentRequests[1]->hasHeader('Authorization'))->toBeFalse();
});

it('does not forward bearer credentials to cross-origin URLs', function () {
    $this->mirror->update([
        'auth_type' => 'bearer',
        'auth_credentials' => ['token' => 'mirror-token'],
    ]);
    $sentRequests = [];

    Http::fake(function (Request $request) use (&$sentRequests) {
        $sentRequests[] = $request;

        return Http::response('ok');
    });

    $client = RegistryClientFactory::createHttpClient($this->mirror->refresh());
    $client->get('https://registry.example.com/metadata');
    $client->get('https://cdn.example.com/archive.zip');

    expect($sentRequests[0]->header('Authorization'))->toBe(['Bearer mirror-token'])
        ->and($sentRequests[1]->hasHeader('Authorization'))->toBeFalse();
});

it('rejects a DNS change before a subsequent request and keeps the first request pinned', function () {
    $this->hostResolver->setSequence('registry.example.com', [
        ['93.184.216.34'],
        ['127.0.0.1'],
    ]);
    $capturedOptions = null;

    Http::fake(function (Request $request, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return Http::response('ok');
    });

    $client = RegistryClientFactory::createHttpClient($this->mirror);
    $client->get('https://registry.example.com/first');

    expect($capturedOptions['curl'][CURLOPT_RESOLVE])->toBe([
        'registry.example.com:443:93.184.216.34',
    ]);

    expect(fn () => $client->get('https://registry.example.com/second'))
        ->toThrow(UnsafeMirrorUrlException::class);

    Http::assertSentCount(1);
});

it('stops after five followed redirects', function () {
    Http::fake(fn () => Http::response('', 302, ['Location' => '/again']));

    expect(fn () => RegistryClientFactory::createHttpClient($this->mirror)
        ->get('https://registry.example.com/start'))
        ->toThrow(UnsafeMirrorUrlException::class, 'maximum number of redirects');

    Http::assertSentCount(6);
});
