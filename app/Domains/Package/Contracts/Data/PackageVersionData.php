<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\GitProvider;
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
        public ?string $commitUrl,
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

        return new self(
            uuid: $version->uuid,
            version: $version->version,
            normalizedVersion: $version->normalized_version,
            releasedAt: $version->released_at,
            sourceUrl: $version->source_url,
            sourceReference: $version->source_reference,
            commitUrl: $commitUrl,
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
        $cleanUrl = preg_replace('/^git@/', '', $sourceUrl);
        $cleanUrl = preg_replace('/\.git$/', '', $cleanUrl);
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

    public function isStable(): bool
    {
        return ! str_contains($this->version, 'dev') && preg_match('/^\d+\.\d+/', $this->version);
    }

    public function isDev(): bool
    {
        return str_contains($this->version, 'dev') || str_starts_with($this->normalizedVersion, 'dev-');
    }
}
