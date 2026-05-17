<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Security\Contracts\Data\SecurityAdvisoryMatchData;
use App\Models\PackageVersion;
use App\Services\Markdown\MarkdownRenderer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;
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
        public ?string $sourceTag,
        public ?string $commitUrl,
        public ?string $tagUrl,
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
        public ?string $readmeHtml = null,
        /** @var SecurityAdvisoryMatchData[]|null */
        public ?array $advisoryMatches = null,
    ) {}

    public static function fromModel(
        PackageVersion $version,
        ?GitProvider $provider = null,
        ?string $repoIdentifier = null,
        ?string $customBaseUrl = null,
    ): self {
        $base = PackageVersionData::fromModel($version, $provider, $repoIdentifier);
        $composerJson = $version->composer_json ?? [];

        $license = $composerJson['license'] ?? null;
        if (is_array($license)) {
            $license = implode(', ', $license);
        }

        $autoload = static::mergeAutoload($composerJson['autoload'] ?? []);

        $advisoryMatchesData = null;
        if ($version->relationLoaded('advisoryMatches') && $version->advisoryMatches->isNotEmpty()) {
            $advisoryMatchesData = $version->advisoryMatches
                ->map(fn ($match) => SecurityAdvisoryMatchData::fromModel($match))
                ->all();
        }

        $readmeHtml = static::renderReadme($version, $provider, $repoIdentifier, $customBaseUrl);

        return new self(
            uuid: $base->uuid,
            version: $base->version,
            normalizedVersion: $base->normalizedVersion,
            releasedAt: $base->releasedAt,
            sourceUrl: $base->sourceUrl,
            sourceReference: $base->sourceReference,
            sourceTag: $base->sourceTag,
            commitUrl: $base->commitUrl,
            tagUrl: $base->tagUrl,
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
            readmeHtml: $readmeHtml,
            advisoryMatches: $advisoryMatchesData,
        );
    }

    protected static function renderReadme(
        PackageVersion $version,
        ?GitProvider $provider,
        ?string $repoIdentifier,
        ?string $customBaseUrl,
    ): ?string {
        if (empty($version->readme)) {
            return null;
        }

        $blobBaseUrl = null;
        $rawFileBaseUrl = null;

        // Prefer the immutable commit SHA over the moving tag/branch name so README
        // links keep pointing at the same content even if the tag is later moved.
        $ref = $version->source_reference ?: $version->source_tag;

        if ($provider !== null && $repoIdentifier !== null && $ref !== null && $ref !== '') {
            $blobBaseUrl = $provider->blobBaseUrl($repoIdentifier, $ref, $customBaseUrl);
            $rawFileBaseUrl = $provider->rawFileBaseUrl($repoIdentifier, $ref, $customBaseUrl);
        }

        return App::make(MarkdownRenderer::class)->render(
            $version->readme,
            $blobBaseUrl,
            $rawFileBaseUrl,
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
