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
     * Create instance from composer.json content.
     */
    public static function fromComposerJson(string $composerJsonContent, string $ref): self
    {
        $data = self::decodeJson($composerJsonContent);
        self::validateRequiredFields($data);

        $version = self::extractVersion($ref);
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

        if (! is_string($data['name']) || ! str_contains($data['name'], '/')) {
            throw new ComposerMetadataException(
                'composer.json name must be in format "vendor/package"'
            );
        }
    }

    /**
     * Extract version from reference (tag or branch name).
     */
    protected static function extractVersion(string $ref): string
    {
        // Remove 'v' prefix if present (e.g., v1.0.0 -> 1.0.0)
        if (str_starts_with($ref, 'v') && preg_match('/^v\d/', $ref)) {
            return substr($ref, 1);
        }

        return $ref;
    }

    /**
     * Normalize version string using Composer's version parser.
     */
    protected static function normalizeVersion(string $version): string
    {
        $versionParser = new VersionParser;

        try {
            // Handle branch names (e.g., main, develop) as dev versions
            if (! preg_match('/^\d+\.\d+/', $version)) {
                return $versionParser->normalize("dev-{$version}");
            }

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
