<?php

use Illuminate\Support\Facades\Artisan;

it('generates a valid OpenAPI specification for the API', function () {
    $path = storage_path('app/openapi-spec-test.json');

    Artisan::call('scramble:export', ['--path' => $path]);

    expect(file_exists($path))->toBeTrue();

    $spec = json_decode((string) file_get_contents($path), true);
    @unlink($path);

    expect($spec['openapi'])->toStartWith('3.');
    expect($spec['info']['title'])->toBe('Pricore API');
    expect($spec['paths'])->not->toBeEmpty();

    // The bearer security scheme is documented and applied globally.
    expect(array_keys($spec['components']['securitySchemes']))->toContain('http');
    expect($spec['components']['securitySchemes']['http']['scheme'])->toBe('bearer');
    expect($spec['security'])->toBe([['http' => []]]);

    // Core resources are present.
    $paths = implode("\n", array_keys($spec['paths']));
    expect($paths)->toContain('organizations');
    expect($paths)->toContain('repositories');
    expect($paths)->toContain('tokens');
});
