<?php

use App\Domains\Repository\Contracts\Data\DistArchiveData;

it('builds the archive path from safe parts', function () {
    expect(DistArchiveData::pathFor('acme', 'vendor/pkg', 'dev-feature/foo', 'abc123def4567890'))
        ->toBe('acme/vendor/pkg/dev-feature/foo_abc123def456.zip');
});

it('refuses parts that could leave the organization directory', function (string $slug, string $name, string $version, string $reference) {
    expect(fn () => DistArchiveData::pathFor($slug, $name, $version, $reference))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'slug' => ['..', 'vendor/pkg', '1.0.0', 'abc'],
    'slug with slash' => ['acme/../other', 'vendor/pkg', '1.0.0', 'abc'],
    'name' => ['acme', '../victim-org', '1.0.0', 'abc'],
    'name with three segments' => ['acme', 'vendor/pkg/extra', '1.0.0', 'abc'],
    'version' => ['acme', 'vendor/pkg', '../../victim-org/pkg/1.0.0', 'abc'],
    'version dot segment' => ['acme', 'vendor/pkg', 'dev-x/./y', 'abc'],
    'version leading slash' => ['acme', 'vendor/pkg', '/1.0.0', 'abc'],
    'version backslash' => ['acme', 'vendor/pkg', '..\\1.0.0', 'abc'],
    'reference' => ['acme', 'vendor/pkg', '1.0.0', '../x'],
]);
