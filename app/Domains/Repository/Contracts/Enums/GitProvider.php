<?php

namespace App\Domains\Repository\Contracts\Enums;

enum GitProvider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Git = 'git';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Git => 'Generic Git',
        };
    }

    public function repositoryUrl(string $repoIdentifier, ?string $baseUrl = null): ?string
    {
        return match ($this) {
            self::GitHub => "https://github.com/{$repoIdentifier}",
            self::GitLab => rtrim($baseUrl ?? 'https://gitlab.com', '/')."/{$repoIdentifier}",
            self::Bitbucket => "https://bitbucket.org/{$repoIdentifier}",
            self::Git => filter_var($repoIdentifier, FILTER_VALIDATE_URL) ? $repoIdentifier : null,
        };
    }

    public function supportsSelfHosted(): bool
    {
        return match ($this) {
            self::GitLab => true,
            self::GitHub, self::Bitbucket, self::Git => false,
        };
    }

    public function supportsWebhooks(): bool
    {
        return match ($this) {
            self::GitHub, self::GitLab, self::Bitbucket, self::Git => true,
        };
    }

    public function supportsAutomaticWebhooks(): bool
    {
        return match ($this) {
            self::GitHub, self::GitLab, self::Bitbucket => true,
            self::Git => false,
        };
    }

    public function webhookRouteName(): string
    {
        return match ($this) {
            self::GitHub => 'webhooks.github',
            self::GitLab => 'webhooks.gitlab',
            self::Bitbucket => 'webhooks.bitbucket',
            self::Git => 'webhooks.git',
        };
    }

    /**
     * Base URL (with trailing slash) for resolving relative <img src> in a repository file.
     *
     * Returns null for providers where the layout is unknown (generic Git).
     */
    public function rawFileBaseUrl(string $repoIdentifier, string $ref, ?string $baseUrl = null): ?string
    {
        return match ($this) {
            self::GitHub => "https://raw.githubusercontent.com/{$repoIdentifier}/{$ref}/",
            self::GitLab => rtrim($baseUrl ?? 'https://gitlab.com', '/')."/{$repoIdentifier}/-/raw/{$ref}/",
            self::Bitbucket => "https://bitbucket.org/{$repoIdentifier}/raw/{$ref}/",
            self::Git => null,
        };
    }

    /**
     * Base URL (with trailing slash) for resolving relative <a href> in a repository file.
     *
     * Returns null for providers where the layout is unknown (generic Git).
     */
    public function blobBaseUrl(string $repoIdentifier, string $ref, ?string $baseUrl = null): ?string
    {
        return match ($this) {
            self::GitHub => "https://github.com/{$repoIdentifier}/blob/{$ref}/",
            self::GitLab => rtrim($baseUrl ?? 'https://gitlab.com', '/')."/{$repoIdentifier}/-/blob/{$ref}/",
            self::Bitbucket => "https://bitbucket.org/{$repoIdentifier}/src/{$ref}/",
            self::Git => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::GitHub->value => self::GitHub->label(),
            self::GitLab->value => self::GitLab->label(),
            self::Bitbucket->value => self::Bitbucket->label(),
        ];
    }
}
