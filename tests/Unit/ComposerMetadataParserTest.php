<?php

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
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

    $result = ComposerMetadataData::fromComposerJson($composerJson, new RefData('v1.0.0', '', isTag: true));

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

    $result = ComposerMetadataData::fromComposerJson($composerJson, new RefData('main', ''));

    expect($result->version)->toBe('dev-main')
        ->and($result->normalizedVersion)->toBe('dev-main');
});

it('names branches as Composer does', function (string $branch, string $expectedVersion, string $expectedNormalizedVersion) {
    $result = ComposerMetadataData::fromComposerJson(json_encode(['name' => 'vendor/package']), new RefData($branch, ''));

    expect($result->version)->toBe($expectedVersion)
        ->and($result->normalizedVersion)->toBe($expectedNormalizedVersion);
})->with([
    'simple branch' => ['develop', 'dev-develop', 'dev-develop'],
    'branch with slash' => ['feature/my-feature', 'dev-feature/my-feature', 'dev-feature/my-feature'],
    'release branch' => ['carconnect-release', 'dev-carconnect-release', 'dev-carconnect-release'],
    'major.minor branch' => ['2.0', '2.0.x-dev', '2.0.9999999.9999999-dev'],
    'major branch' => ['2', '2.x-dev', '2.9999999.9999999.9999999-dev'],
    'wildcard branch' => ['1.2.x', '1.2.x-dev', '1.2.9999999.9999999-dev'],
    'v-prefixed branch' => ['v2.1', 'v2.1.x-dev', '2.1.9999999.9999999-dev'],
    'full version branch' => ['1.2.3', '1.2.3.x-dev', '1.2.3.9999999-dev'],
    'version-like branch with suffix' => ['2.0-feature', 'dev-2.0-feature', 'dev-2.0-feature'],
]);

it('keeps version tags verbatim and prefixes other tags', function (string $tag, string $expectedVersion, string $expectedNormalizedVersion) {
    $result = ComposerMetadataData::fromComposerJson(json_encode(['name' => 'vendor/package']), new RefData($tag, '', isTag: true));

    expect($result->version)->toBe($expectedVersion)
        ->and($result->normalizedVersion)->toBe($expectedNormalizedVersion);
})->with([
    'tag version' => ['v1.0.0', 'v1.0.0', '1.0.0.0'],
    'tag without v' => ['1.2.3', '1.2.3', '1.2.3.0'],
    'major.minor tag' => ['2.0', '2.0', '2.0.0.0'],
    'non-version tag' => ['nightly', 'dev-nightly', 'dev-nightly'],
]);

it('gives a tag and a branch of the same name different versions', function () {
    $tag = ComposerMetadataData::extractVersion(new RefData('2.0', '', isTag: true));
    $branch = ComposerMetadataData::extractVersion(new RefData('2.0', ''));

    expect($tag)->toBe('2.0')
        ->and($branch)->toBe('2.0.x-dev');
});

it('throws exception for invalid JSON', function () {
    ComposerMetadataData::fromComposerJson('invalid json', new RefData('v1.0.0', '', isTag: true));
})->throws(ComposerMetadataException::class);

it('throws exception for missing name field', function () {
    $composerJson = json_encode([
        'description' => 'Test package',
    ]);

    ComposerMetadataData::fromComposerJson($composerJson, new RefData('v1.0.0', '', isTag: true));
})->throws(ComposerMetadataException::class);

it('throws exception for invalid name format', function () {
    $composerJson = json_encode([
        'name' => 'invalidname',
    ]);

    ComposerMetadataData::fromComposerJson($composerJson, new RefData('v1.0.0', '', isTag: true));
})->throws(ComposerMetadataException::class);

it('rejects names that are not two plain path segments', function (string $name) {
    ComposerMetadataData::fromComposerJson(json_encode(['name' => $name]), new RefData('v1.0.0', '', isTag: true));
})->with([
    '../other-org/package',
    'vendor/..',
    './package',
    'vendor/package/extra',
    '/vendor/package',
    'vendor\\package',
])->throws(ComposerMetadataException::class);

it('keeps accepting names Composer itself would flag, such as uppercase', function () {
    $result = ComposerMetadataData::fromComposerJson(json_encode(['name' => 'Vendor/My.Package_name']), new RefData('v1.0.0', '', isTag: true));

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

    $result = ComposerMetadataData::fromComposerJson($composerJson, new RefData('v1.0.0', '', isTag: true));

    expect($result->type)->toBe('library');
});
