<?php

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\DistArchiveData;
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
        ->and($result->version)->toBe('v1.0.0')
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
    'tag version' => ['v1.0.0', 'v1.0.0'],
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

it('rejects names that are not two plain path segments', function (string $name) {
    ComposerMetadataData::fromComposerJson(json_encode(['name' => $name]), 'v1.0.0');
})->with([
    '../other-org/package',
    'vendor/..',
    './package',
    'vendor/package/extra',
    '/vendor/package',
    'vendor\\package',
])->throws(ComposerMetadataException::class);

it('keeps accepting names Composer itself would flag, such as uppercase', function () {
    $result = ComposerMetadataData::fromComposerJson(json_encode(['name' => 'Vendor/My.Package_name']), 'v1.0.0');

    expect($result->name)->toBe('Vendor/My.Package_name');
});

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

it('rejects refs that would step out of the package directory on the dist disk', function (string $ref) {
    ComposerMetadataData::fromComposerJson(json_encode(['name' => 'vendor/package']), $ref);
})->with([
    '1.0.0/../../../../victim-org/vendor/pkg/1.0.0',
    'feature/../../other',
    'feature//double',
    'feature/',
    'main\\..\\other',
])->throws(ComposerMetadataException::class);

it('builds dist paths for tags and nested branch names only', function () {
    expect(DistArchiveData::pathFor('acme', 'vendor/package', 'dev-feature/login', 'abcdef1234567890'))
        ->toBe('acme/vendor/package/dev-feature/login_abcdef123456.zip');

    expect(fn () => DistArchiveData::pathFor('acme', 'vendor/package', '1.0.0/../../../../victim/vendor/pkg/1.0.0', 'abcdef1234567890'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DistArchiveData::pathFor('acme', '../../victim/pkg', '1.0.0', 'abcdef1234567890'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DistArchiveData::pathFor('acme', 'vendor/package', '1.0.0', '../../../x'))
        ->toThrow(InvalidArgumentException::class);
});
