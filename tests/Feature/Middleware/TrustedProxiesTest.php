<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/forwarded', fn (Request $request) => [
        'secure' => $request->isSecure(),
        'ip' => $request->ip(),
    ]);
});

function forwardedRequest(): array
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->get('/_test/forwarded', [
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ])
        ->assertOk()
        ->json();
}

it('trusts forwarded headers from a configured proxy', function (string $proxies) {
    config(['trustedproxy.proxies' => $proxies]);

    expect(forwardedRequest())->toBe(['secure' => true, 'ip' => '203.0.113.7']);
})->with([
    'single address' => '10.0.0.5',
    'list with a range' => '192.168.1.0/24, 10.0.0.0/8',
    'wildcard' => '*',
]);

it('ignores forwarded headers when the proxy is not trusted', function (string $proxies) {
    config(['trustedproxy.proxies' => $proxies]);

    expect(forwardedRequest())->toBe(['secure' => false, 'ip' => '10.0.0.5']);
})->with([
    'not configured' => '',
    'other address' => '192.168.1.1',
]);
