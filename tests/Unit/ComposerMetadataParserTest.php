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

    expect($result->version)->toBe('main')
        ->and($result->normalizedVersion)->toContain('dev-main');
});

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
