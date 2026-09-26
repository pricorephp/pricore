<?php

use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;

it('normalizes surrounding whitespace and slashes', function () {
    expect(PackagePathPattern::normalize(' /packages/billing/ '))->toBe('packages/billing')
        ->and(PackagePathPattern::normalize('packages//billing'))->toBe('packages/billing')
        ->and(PackagePathPattern::normalize('./packages/*'))->toBe('packages/*')
        ->and(PackagePathPattern::normalize('.'))->toBe('.')
        ->and(PackagePathPattern::normalize('/'))->toBe('.')
        ->and(PackagePathPattern::normalize('./'))->toBe('.')
        ->and(PackagePathPattern::normalize(''))->toBe('');
});

it('accepts directories, single-level wildcards and the root', function () {
    foreach (['packages/billing', 'packages/*', '*', '.', '/', 'src/Symfony/Component/Console', 'lib_v2/pkg-1.0'] as $pattern) {
        expect(PackagePathPattern::isValid($pattern))->toBeTrue($pattern);
    }
});

it('rejects traversal, nested wildcards and unsafe characters', function () {
    foreach (['', '../x', 'packages/../x', 'packages/*/src', 'packages/**', 'a b', 'packages/bil ling', "x\0y", 'packages/*x'] as $pattern) {
        expect(PackagePathPattern::isValid($pattern))->toBeFalse($pattern);
    }
});

it('identifies wildcards and their parent directory', function () {
    expect(PackagePathPattern::isWildcard('packages/*'))->toBeTrue()
        ->and(PackagePathPattern::isWildcard('*'))->toBeTrue()
        ->and(PackagePathPattern::isWildcard('packages/billing'))->toBeFalse()
        ->and(PackagePathPattern::wildcardParent('packages/*'))->toBe('packages')
        ->and(PackagePathPattern::wildcardParent('src/packages/*'))->toBe('src/packages')
        ->and(PackagePathPattern::wildcardParent('*'))->toBe('');
});

it('matches package directories against patterns', function () {
    expect(PackagePathPattern::matches('.', null))->toBeTrue()
        ->and(PackagePathPattern::matches('.', ''))->toBeTrue()
        ->and(PackagePathPattern::matches('.', 'packages/billing'))->toBeFalse()
        ->and(PackagePathPattern::matches('packages/*', 'packages/billing'))->toBeTrue()
        ->and(PackagePathPattern::matches('packages/*', 'packages/billing/sub'))->toBeFalse()
        ->and(PackagePathPattern::matches('packages/*', 'other/billing'))->toBeFalse()
        ->and(PackagePathPattern::matches('packages/*', null))->toBeFalse()
        ->and(PackagePathPattern::matches('*', 'billing'))->toBeTrue()
        ->and(PackagePathPattern::matches('*', 'packages/billing'))->toBeFalse()
        ->and(PackagePathPattern::matches('packages/billing', 'packages/billing'))->toBeTrue()
        ->and(PackagePathPattern::matches('packages/billing', 'packages/billing2'))->toBeFalse()
        ->and(PackagePathPattern::anyMatches(['.', 'packages/*'], 'packages/crm'))->toBeTrue()
        ->and(PackagePathPattern::anyMatches(['packages/*'], null))->toBeFalse();
});
