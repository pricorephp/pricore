<?php

namespace App\Domains\Repository\Services\PackagePaths;

/**
 * A configured package location inside a repository: a directory such as
 * "packages/billing", a single-level wildcard such as "packages/*", or "."
 * for the repository root. Packages store their directory without the root
 * marker (null for the root), so matching accepts that form as well.
 */
final class PackagePathPattern
{
    public const ROOT = '.';

    protected const SEGMENT = '/^[A-Za-z0-9._-]+$/';

    public static function normalize(string $pattern): string
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return '';
        }

        $pattern = preg_replace('#/+#', '/', $pattern) ?? $pattern;
        $pattern = preg_replace('#^(\./)+#', '', trim($pattern, '/')) ?? $pattern;
        $pattern = trim($pattern, '/');

        return $pattern === '' || $pattern === self::ROOT ? self::ROOT : $pattern;
    }

    public static function isValid(string $pattern): bool
    {
        $pattern = self::normalize($pattern);

        if ($pattern === '') {
            return false;
        }

        if ($pattern === self::ROOT) {
            return true;
        }

        $segments = explode('/', $pattern);
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($segment === '*') {
                if ($index !== $last) {
                    return false;
                }

                continue;
            }

            if (! self::isValidSegment($segment)) {
                return false;
            }
        }

        return true;
    }

    public static function isValidSegment(string $segment): bool
    {
        return $segment !== '.' && $segment !== '..' && preg_match(self::SEGMENT, $segment) === 1;
    }

    public static function isWildcard(string $pattern): bool
    {
        return $pattern === '*' || str_ends_with($pattern, '/*');
    }

    /**
     * Directory whose children a wildcard pattern selects ('' for the root).
     */
    public static function wildcardParent(string $pattern): string
    {
        return $pattern === '*' ? '' : substr($pattern, 0, -2);
    }

    /**
     * Whether a package directory (null or '' for the root) is selected by the pattern.
     */
    public static function matches(string $pattern, ?string $path): bool
    {
        $pattern = self::normalize($pattern);
        $path = trim((string) $path, '/');

        if ($pattern === self::ROOT) {
            return $path === '';
        }

        if ($path === '') {
            return false;
        }

        if (self::isWildcard($pattern)) {
            $slash = strrpos($path, '/');
            $parent = $slash === false ? '' : substr($path, 0, $slash);

            return $parent === self::wildcardParent($pattern);
        }

        return $pattern === $path;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    public static function anyMatches(array $patterns, ?string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
