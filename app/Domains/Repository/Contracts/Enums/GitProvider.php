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
