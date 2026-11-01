<?php

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Services\TagFormats\TagFormat;

uses()->group('tag-format');

function tagTarget(?string $template, string $ref, bool $isTag = true): ?array
{
    $target = TagFormat::target($template === null ? null : TagFormat::fromTemplate($template), new RefData($ref, 'abc', $isTag));

    return $target === null ? null : [$target->packageKey, $target->version];
}

it('accepts templates with a version and one package placeholder', function (string $template, bool $valid) {
    expect(TagFormat::isValid($template))->toBe($valid);
})->with([
    ['{package}@{version}', true],
    ['{path}/{version}', true],
    ['{package}-{version}', true],
    ['release/{name}/{version}', true],
    ['{version}-{package}', true],
    ['{version}', false],
    ['{package}', false],
    ['{package}{version}', false],
    ['{package}@{version}@{name}', false],
    ['{package}@{version}}', false],
    ['{unknown}@{version}', false],
    ['{package} {version}', false],
    ['', false],
]);

it('splits tags into the package and its version', function (string $template, string $tag, ?array $expected) {
    expect(tagTarget($template, $tag))->toBe($expected);
})->with([
    'package@version' => ['{package}@{version}', 'billing@1.2.0', ['billing', '1.2.0']],
    'pre-release' => ['{package}@{version}', 'billing@1.2.0-RC1', ['billing', '1.2.0-RC1']],
    'path prefix' => ['{path}/{version}', 'packages/billing/v1.0.0', ['packages/billing', 'v1.0.0']],
    'full name' => ['release/{name}/{version}', 'release/acme/billing/2.0.0', ['acme/billing', '2.0.0']],
    'dash ending in a digit' => ['{package}-{version}', 'billing-v2-v1.0.0', ['billing-v2', 'v1.0.0']],
    'version first' => ['{version}-{package}', '1.0.0-billing', ['billing', '1.0.0']],
    'plain version stays shared' => ['{package}-{version}', 'v1.0.0', [null, 'v1.0.0']],
    'loose version stays shared' => ['{package}@{version}', 'v1.0.0-hotfix', [null, 'v1.0.0-hotfix']],
    'other tags release nothing' => ['{package}@{version}', 'nightly', null],
]);

it('keeps branches and repositories without a format shared', function () {
    expect(tagTarget('{package}@{version}', 'main', isTag: false))->toBe([null, 'dev-main'])
        ->and(tagTarget(null, 'billing@1.0.0'))->toBe([null, 'dev-billing@1.0.0'])
        ->and(tagTarget(null, 'v1.0.0'))->toBe([null, 'v1.0.0']);
});

it('derives the value a package carries in its tags', function () {
    expect(TagFormat::fromTemplate('{name}@{version}')->keyFor('packages/billing', 'Acme/Billing'))->toBe('acme/billing')
        ->and(TagFormat::fromTemplate('{package}@{version}')->keyFor('packages/billing', 'acme/billing'))->toBe('billing')
        ->and(TagFormat::fromTemplate('{path}/{version}')->keyFor('packages/billing', 'acme/billing'))->toBe('packages/billing')
        ->and(TagFormat::fromTemplate('{path}/{version}')->keyFor(null, 'acme/monorepo'))->toBeNull();
});
