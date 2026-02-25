<?php

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Exceptions\ComposerMetadataException;

it('parses valid composer.json with tag version', function () {
    $composerJson = json_encode([
        'name' => 'vendor/package',
        'description' => 'Test package',
        'type' => 'library',
        'require' => [
            'php' => '^8.2',
        ],
    ]);

    $result = ComposerMetadataData::fromComposerJson($composerJson, 'v1.0.0');

    expect($result)->toBeInstanceOf(ComposerMetadataData::class)
        ->and($result->name)->toBe('vendor/package')
        ->and($result->version)->toBe('1.0.0')
        ->and($result->normalizedVersion)->toBe('1.0.0.0')
        ->and($result->type)->toBe('library')
        ->and($result->description)->toBe('Test package')
        ->and($result->composerJson)->toBeArray()
        ->and($result->composerJson['name'])->toBe('vendor/package');
});

it('parses composer.json with branch name', function () {
    $composerJson = json_encode([
        'name' => 'vendor/package',
        'type' => 'library',
    ]);

    $result = ComposerMetadataData::fromComposerJson($composerJson, 'main');

    expect($result->version)->toBe('dev-main')
        ->and($result->normalizedVersion)->toBe('dev-main');
});

it('adds dev- prefix to non-semver branch names', function (string $ref, string $expectedVersion) {
    $composerJson = json_encode([
        'name' => 'vendor/package',
        'type' => 'library',
    ]);

    $result = ComposerMetadataData::fromComposerJson($composerJson, $ref);

    expect($result->version)->toBe($expectedVersion);
})->with([
    'simple branch' => ['develop', 'dev-develop'],
    'branch with slash' => ['feature/my-feature', 'dev-feature/my-feature'],
    'release branch' => ['carconnect-release', 'dev-carconnect-release'],
    'tag version' => ['v1.0.0', '1.0.0'],
    'tag without v' => ['1.2.3', '1.2.3'],
]);

it('throws exception for invalid JSON', function () {
    ComposerMetadataData::fromComposerJson('invalid json', 'v1.0.0');
})->throws(ComposerMetadataException::class);

it('throws exception for missing name field', function () {
    $composerJson = json_encode([
        'description' => 'Test package',
    ]);

    ComposerMetadataData::fromComposerJson($composerJson, 'v1.0.0');
})->throws(ComposerMetadataException::class);

it('throws exception for invalid name format', function () {
    $composerJson = json_encode([
        'name' => 'invalidname',
    ]);

    ComposerMetadataData::fromComposerJson($composerJson, 'v1.0.0');
})->throws(ComposerMetadataException::class);

it('extracts package name from composer.json data', function () {
    $composerJson = [
        'name' => 'vendor/package',
        'description' => 'Test',
    ];

    $name = ComposerMetadataData::extractPackageName($composerJson);

    expect($name)->toBe('vendor/package');
});

it('defaults type to library when not specified', function () {
    $composerJson = json_encode([
        'name' => 'vendor/package',
    ]);

    $result = ComposerMetadataData::fromComposerJson($composerJson, 'v1.0.0');

    expect($result->type)->toBe('library');
});
