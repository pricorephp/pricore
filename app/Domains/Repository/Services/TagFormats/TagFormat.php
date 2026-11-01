<?php

namespace App\Domains\Repository\Services\TagFormats;

use App\Domains\Repository\Contracts\Data\ComposerMetadataData;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefTargetData;
use App\Models\Repository;
use Illuminate\Support\Str;

/**
 * How a monorepo tags the release of a single package, such as "{package}@{version}"
 * or "{path}/{version}". A tag in this format releases only the package it names,
 * at the version that follows. Plain version tags such as "v1.0.0" still release
 * every package, and branches stay shared.
 */
class TagFormat
{
    public const VERSION = '{version}';

    /** The full Composer name, e.g. acme/billing */
    public const NAME = '{name}';

    /** The Composer name without the vendor, e.g. billing */
    public const PACKAGE = '{package}';

    /** The package directory in the repository, e.g. packages/billing */
    public const PATH = '{path}';

    public const PACKAGE_PLACEHOLDERS = [self::NAME, self::PACKAGE, self::PATH];

    // Versions need at least major.minor, like shared tags, plus Composer's stability
    // and -dev suffixes, as in 1.2.0-beta1-dev
    protected const VERSION_PATTERN = 'v?\d+(?:\.\d+){1,3}(?:[._-]?(?:stable|beta|b|RC|rc|alpha|a|patch|pl|p)(?:[.-]?\d+)*)?(?:[.-]?dev)?(?:\+[0-9A-Za-z.-]+)?';

    // Lazy, so the package part ends at the first separator the version can follow
    protected const KEY_PATTERNS = [
        self::NAME => '[a-z0-9][a-z0-9_.-]*?\/[a-z0-9][a-z0-9_.-]*?',
        self::PACKAGE => '[a-z0-9][a-z0-9_.-]*?',
        self::PATH => '[A-Za-z0-9._-]+?(?:\/[A-Za-z0-9._-]+?)*?',
    ];

    protected function __construct(
        public readonly string $template,
        public readonly string $placeholder,
        protected readonly string $regex,
    ) {}

    public static function forRepository(Repository $repository): ?self
    {
        $template = $repository->tag_format;

        return $template !== null && self::isValid($template) ? self::fromTemplate($template) : null;
    }

    /**
     * A template holds {version} and one package placeholder, separated by at
     * least one character so the two can be told apart.
     */
    public static function isValid(string $template): bool
    {
        if ($template === '' || strlen($template) > 255 || preg_match('/\s/', $template) === 1) {
            return false;
        }

        preg_match_all('/\{[^{}]*\}/', $template, $matches, PREG_OFFSET_CAPTURE);
        $placeholders = array_column($matches[0], 0);

        if (count($placeholders) !== 2
            || count(array_keys($placeholders, self::VERSION, true)) !== 1
            || count(array_intersect($placeholders, self::PACKAGE_PLACEHOLDERS)) !== 1) {
            return false;
        }

        // No stray braces outside the placeholders
        if (str_contains((string) preg_replace('/\{[^{}]*\}/', '', $template), '{')
            || str_contains((string) preg_replace('/\{[^{}]*\}/', '', $template), '}')) {
            return false;
        }

        [$first, $second] = $matches[0];

        return $second[1] > $first[1] + strlen($first[0]);
    }

    public static function fromTemplate(string $template): self
    {
        if (! self::isValid($template)) {
            throw new \InvalidArgumentException("Invalid tag format [{$template}].");
        }

        $placeholder = self::NAME;
        $regex = '';

        foreach (preg_split('/(\{[^{}]*\})/', $template, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ($part === self::VERSION) {
                $regex .= '(?<version>'.self::VERSION_PATTERN.')';
            } elseif (isset(self::KEY_PATTERNS[$part])) {
                $placeholder = $part;
                $regex .= '(?<key>'.self::KEY_PATTERNS[$part].')';
            } else {
                $regex .= preg_quote($part, '~');
            }
        }

        return new self($template, $placeholder, "~^{$regex}$~");
    }

    /**
     * What a ref releases, or null for a tag that is neither in this format nor
     * a version (and so releases nothing).
     */
    public static function target(?self $format, RefData $ref): ?RefTargetData
    {
        $sharedVersion = ComposerMetadataData::extractVersion($ref->name);

        if ($format === null || ! $ref->isTag || self::isPlainVersion($ref->name)) {
            return new RefTargetData($sharedVersion);
        }

        return $format->match($ref->name)
            ?? (str_starts_with($sharedVersion, 'dev-') ? null : new RefTargetData($sharedVersion));
    }

    /**
     * Whether a version came from a tag of a single package that no longer exists.
     * A branch can carry the same name, but its versions are dev-<name>, so the
     * version must be the one that tag releases.
     *
     * @param  array<int, string>  $tagNames
     */
    public static function ownedByDeletedPackageTag(?self $format, ?string $sourceTag, string $version, array $tagNames): bool
    {
        if ($format === null || $sourceTag === null || in_array($sourceTag, $tagNames, true)) {
            return false;
        }

        $owner = self::target($format, new RefData($sourceTag, '', isTag: true));

        return $owner !== null && ! $owner->isShared() && $owner->version === $version;
    }

    public function match(string $tag): ?RefTargetData
    {
        if (preg_match($this->regex, $tag, $matches) !== 1) {
            return null;
        }

        return new RefTargetData(version: $matches['version'], packageKey: $matches['key']);
    }

    /**
     * The value a package's tags carry in place of the placeholder; null for the
     * root package under {path}, which only takes part in shared tags.
     */
    public function keyFor(?string $path, string $packageName): ?string
    {
        return match ($this->placeholder) {
            self::NAME => strtolower($packageName),
            self::PACKAGE => strtolower(Str::afterLast($packageName, '/')),
            default => $path === null || $path === '' ? null : $path,
        };
    }

    protected static function isPlainVersion(string $tag): bool
    {
        return preg_match('~^'.self::VERSION_PATTERN.'$~', $tag) === 1;
    }
}
