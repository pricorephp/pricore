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

    public function repositoryUrl(string $repoIdentifier): ?string
    {
        return match ($this) {
            self::GitHub => "https://github.com/{$repoIdentifier}",
            self::GitLab => "https://gitlab.com/{$repoIdentifier}",
            self::Bitbucket => "https://bitbucket.org/{$repoIdentifier}",
            self::Git => filter_var($repoIdentifier, FILTER_VALIDATE_URL) ? $repoIdentifier : null,
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
            self::Git->value => self::Git->label(),
        ];
    }
}
