<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageVersionDetailData extends Data
{
    /**
     * @param  array<string, string>|null  $require
     * @param  array<string, string>|null  $requireDev
     * @param  array<string, string>|null  $autoload
     * @param  array<int, array{name?: string, email?: string, homepage?: string}>|null  $authors
     * @param  array<int, string>|null  $keywords
     */
    public function __construct(
        public string $uuid,
        public string $version,
        public string $normalizedVersion,
        public ?CarbonInterface $releasedAt,
        public ?string $sourceUrl,
        public ?string $sourceReference,
        public ?string $commitUrl,
        public ?string $description,
        public ?string $type,
        public ?string $license,
        public ?array $require,
        public ?array $requireDev,
        public ?array $autoload,
        public ?array $authors,
        public ?array $keywords,
        public bool $isStable,
        public bool $isDev,
    ) {}

    public static function fromModel(
        PackageVersion $version,
        ?GitProvider $provider = null,
        ?string $repoIdentifier = null
    ): self {
        $base = PackageVersionData::fromModel($version, $provider, $repoIdentifier);
        $composerJson = $version->composer_json ?? [];

        $license = $composerJson['license'] ?? null;
        if (is_array($license)) {
            $license = implode(', ', $license);
        }

        $autoload = static::mergeAutoload($composerJson['autoload'] ?? []);

        return new self(
            uuid: $base->uuid,
            version: $base->version,
            normalizedVersion: $base->normalizedVersion,
            releasedAt: $base->releasedAt,
            sourceUrl: $base->sourceUrl,
            sourceReference: $base->sourceReference,
            commitUrl: $base->commitUrl,
            description: $composerJson['description'] ?? null,
            type: $composerJson['type'] ?? null,
            license: $license,
            require: ! empty($composerJson['require']) ? $composerJson['require'] : null,
            requireDev: ! empty($composerJson['require-dev']) ? $composerJson['require-dev'] : null,
            autoload: ! empty($autoload) ? $autoload : null,
            authors: ! empty($composerJson['authors']) ? $composerJson['authors'] : null,
            keywords: ! empty($composerJson['keywords']) ? $composerJson['keywords'] : null,
            isStable: $base->isStable(),
            isDev: $base->isDev(),
        );
    }

    /**
     * Merge psr-4 and psr-0 autoload entries into a flat namespace → path map.
     *
     * @param  array<string, mixed>  $autoload
     * @return array<string, string>
     */
    protected static function mergeAutoload(array $autoload): array
    {
        $merged = [];

        foreach (['psr-4', 'psr-0'] as $standard) {
            if (isset($autoload[$standard]) && is_array($autoload[$standard])) {
                foreach ($autoload[$standard] as $namespace => $path) {
                    $merged[$namespace] = is_array($path) ? implode(', ', $path) : $path;
                }
            }
        }

        return $merged;
    }
}
