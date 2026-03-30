<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageVersionData extends Data
{
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
        public ?int $distSize,
        public int $vulnerabilityCount = 0,
        public ?AdvisorySeverity $highestSeverity = null,
    ) {}

    public static function fromModel(
        PackageVersion $version,
        ?GitProvider $provider = null,
        ?string $repoIdentifier = null
    ): self {
        $commitUrl = null;

        if ($version->source_reference && $version->source_url) {
            // Use provided provider/repoIdentifier, or try to get from version's package relationship
            $versionProvider = $provider ?? $version->package->repository?->provider;
            $versionRepoIdentifier = $repoIdentifier ?? $version->package->repository?->repo_identifier;

            $commitUrl = static::generateCommitUrl(
                $version->source_url,
                $version->source_reference,
                $versionProvider,
                $versionRepoIdentifier,
            );
        }

        $vulnerabilityCount = $version->relationLoaded('advisoryMatches')
            ? $version->advisoryMatches->count()
            : 0;

        $highestSeverity = null;
        if ($vulnerabilityCount > 0) {
            $highestWeight = $version->advisoryMatches
                ->max(fn ($match) => $match->advisory->severity->weight());
            $highestSeverity = $highestWeight ? AdvisorySeverity::fromWeight($highestWeight) : null;
        }

        $tagUrl = null;

        if ($version->source_tag && $version->source_url) {
            $versionProvider ??= $version->package->repository?->provider;
            $versionRepoIdentifier ??= $version->package->repository?->repo_identifier;

            $tagUrl = static::generateTagUrl(
                sourceUrl: $version->source_url,
                sourceTag: $version->source_tag,
                version: $version->version,
                provider: $versionProvider,
                repoIdentifier: $versionRepoIdentifier,
            );
        }

        return new self(
            uuid: $version->uuid,
            version: $version->version,
            normalizedVersion: $version->normalized_version,
            releasedAt: $version->released_at,
            sourceUrl: $version->source_url,
            sourceReference: $version->source_reference,
            sourceTag: $version->source_tag,
            commitUrl: $commitUrl,
            tagUrl: $tagUrl,
            distSize: $version->dist_size,
            vulnerabilityCount: $vulnerabilityCount,
            highestSeverity: $highestSeverity,
        );
    }

    protected static function generateCommitUrl(
        ?string $sourceUrl,
        string $commitSha,
        ?GitProvider $provider,
        ?string $repoIdentifier
    ): ?string {
        if (! $sourceUrl || ! $commitSha) {
            return null;
        }

        // If we have provider and repo identifier, use them for accurate URL generation
        if ($provider && $repoIdentifier) {
            return match ($provider) {
                GitProvider::GitHub => "https://github.com/{$repoIdentifier}/tree/{$commitSha}",
                GitProvider::GitLab => "https://gitlab.com/{$repoIdentifier}/-/tree/{$commitSha}",
                GitProvider::Bitbucket => "https://bitbucket.org/{$repoIdentifier}/src/{$commitSha}",
                GitProvider::Git => static::generateCommitUrlFromSourceUrl($sourceUrl, $commitSha),
            };
        }

        // Fallback: try to infer from source URL
        return static::generateCommitUrlFromSourceUrl($sourceUrl, $commitSha);
    }

    protected static function generateCommitUrlFromSourceUrl(
        string $sourceUrl,
        string $commitSha
    ): ?string {
        // Remove git@ prefix and .git suffix
        $cleanUrl = preg_replace('/^git@/', '', $sourceUrl) ?? '';
        $cleanUrl = preg_replace('/\.git$/', '', $cleanUrl) ?? '';
        $cleanUrl = str_replace(':', '/', $cleanUrl);

        if (str_starts_with($cleanUrl, 'github.com/')) {
            return "https://{$cleanUrl}/tree/{$commitSha}";
        }

        if (str_starts_with($cleanUrl, 'gitlab.com/')) {
            return "https://{$cleanUrl}/-/tree/{$commitSha}";
        }

        if (str_starts_with($cleanUrl, 'bitbucket.org/')) {
            return "https://{$cleanUrl}/src/{$commitSha}";
        }

        // For generic git URLs, try to construct a tree URL
        // If it's already an HTTPS URL, append /tree/{sha}
        if (str_starts_with($sourceUrl, 'http://') || str_starts_with($sourceUrl, 'https://')) {
            $url = parse_url($sourceUrl);
            $path = rtrim($url['path'] ?? '', '.git');

            return ($url['scheme'] ?? 'https').'://'.($url['host'] ?? '').$path.'/tree/'.$commitSha;
        }

        return null;
    }

    protected static function generateTagUrl(
        ?string $sourceUrl,
        string $sourceTag,
        string $version,
        ?GitProvider $provider,
        ?string $repoIdentifier
    ): ?string {
        // Dev branches should not get tag URLs
        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return null;
        }

        if ($provider && $repoIdentifier) {
            return match ($provider) {
                GitProvider::GitHub => "https://github.com/{$repoIdentifier}/releases/tag/{$sourceTag}",
                GitProvider::GitLab => "https://gitlab.com/{$repoIdentifier}/-/tags/{$sourceTag}",
                GitProvider::Bitbucket => "https://bitbucket.org/{$repoIdentifier}/src/{$sourceTag}",
                GitProvider::Git => static::generateTagUrlFromSourceUrl($sourceUrl, $sourceTag),
            };
        }

        return static::generateTagUrlFromSourceUrl($sourceUrl, $sourceTag);
    }

    protected static function generateTagUrlFromSourceUrl(
        ?string $sourceUrl,
        string $sourceTag
    ): ?string {
        if (! $sourceUrl) {
            return null;
        }

        $cleanUrl = preg_replace('/^git@/', '', $sourceUrl) ?? '';
        $cleanUrl = preg_replace('/\.git$/', '', $cleanUrl) ?? '';
        $cleanUrl = str_replace(':', '/', $cleanUrl);

        if (str_starts_with($cleanUrl, 'github.com/')) {
            return "https://{$cleanUrl}/releases/tag/{$sourceTag}";
        }

        if (str_starts_with($cleanUrl, 'gitlab.com/')) {
            return "https://{$cleanUrl}/-/tags/{$sourceTag}";
        }

        if (str_starts_with($cleanUrl, 'bitbucket.org/')) {
            return "https://{$cleanUrl}/src/{$sourceTag}";
        }

        return null;
    }

    public function isStable(): bool
    {
        return ! str_contains($this->version, 'dev') && preg_match('/^\d+\.\d+/', $this->version);
    }

    public function isDev(): bool
    {
        return str_contains($this->version, 'dev') || str_starts_with($this->normalizedVersion, 'dev-');
    }
}
