<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Exceptions\ComposerMetadataException;
use Composer\Semver\VersionParser;
use Spatie\LaravelData\Data;

class ComposerMetadataData extends Data
{
    /**
     * @param  array<string, mixed>  $composerJson
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $normalizedVersion,
        public array $composerJson,
        public string $type,
        public ?string $description,
    ) {}

    /**
     * The version comes from the ref, unless the caller already derived it,
     * such as from a tag that releases a single package of a monorepo.
     */
    public static function fromComposerJson(string $composerJsonContent, RefData $ref, ?string $version = null): self
    {
        $data = self::decodeJson($composerJsonContent);
        self::validateRequiredFields($data);

        $version ??= self::extractVersion($ref);
        $normalizedVersion = self::normalizeVersion($version);

        return new self(
            name: $data['name'],
            version: $version,
            normalizedVersion: $normalizedVersion,
            composerJson: $data,
            type: $data['type'] ?? 'library',
            description: $data['description'] ?? null,
        );
    }

    /**
     * Decode JSON content.
     *
     * @return array<string, mixed>
     */
    protected static function decodeJson(string $content): array
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                throw new ComposerMetadataException('composer.json must be a JSON object');
            }

            return $data;
        } catch (\JsonException $e) {
            throw new ComposerMetadataException(
                "Invalid JSON in composer.json: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Validate that required fields are present.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function validateRequiredFields(array $data): void
    {
        if (! isset($data['name'])) {
            throw new ComposerMetadataException('composer.json is missing required field: name');
        }

        if (! is_string($data['name']) || ! self::isPathSafeName($data['name'])) {
            throw new ComposerMetadataException(
                'composer.json name must be in format "vendor/package"'
            );
        }
    }

    /**
     * Package names become directories on the dist disk, so a name must be
     * exactly two plain segments. Looser than Composer's own rule, which
     * existing packages with uppercase names would fail.
     */
    public static function isPathSafeName(string $name): bool
    {
        if (preg_match('{^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$}', $name) !== 1) {
            return false;
        }

        return array_intersect(explode('/', $name), ['.', '..']) === [];
    }

    /**
     * Version tags are kept verbatim (v1.0.0 stays v1.0.0) so the pretty version
     * matches the Git tag; other tags get a dev- prefix. Branches follow Composer:
     * a numeric branch such as 1.2 or 1.2.x becomes 1.2.x-dev, any other dev-<name>,
     * so a branch never poses as a release.
     */
    public static function extractVersion(RefData $ref): string
    {
        if ($ref->isTag) {
            return preg_match('/^v?\d+\.\d+/', $ref->name) === 1 ? $ref->name : "dev-{$ref->name}";
        }

        $normalizedBranch = (new VersionParser)->normalizeBranch($ref->name);

        if (str_starts_with($normalizedBranch, 'dev-')) {
            return "dev-{$ref->name}";
        }

        $prefix = str_starts_with($ref->name, 'v') ? 'v' : '';

        return $prefix.preg_replace('/(\.9{7})+/', '.x', $normalizedBranch);
    }

    /**
     * Normalize version string using Composer's version parser.
     */
    protected static function normalizeVersion(string $version): string
    {
        $versionParser = new VersionParser;

        try {
            return $versionParser->normalize($version);
        } catch (\Exception $e) {
            // If version parsing fails, treat as dev version
            return $versionParser->normalize("dev-{$version}");
        }
    }

    /**
     * Extract package name from composer.json data.
     *
     * @param  array<string, mixed>  $composerJson
     */
    public static function extractPackageName(array $composerJson): string
    {
        if (! isset($composerJson['name'])) {
            throw new ComposerMetadataException('composer.json is missing required field: name');
        }

        return $composerJson['name'];
    }
}
