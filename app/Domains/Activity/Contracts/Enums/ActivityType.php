<?php

namespace App\Domains\Activity\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ActivityType: string
{
    case RepositoryAdded = 'repository.added';
    case RepositoryRemoved = 'repository.removed';
    case RepositorySynced = 'repository.synced';
    case RepositorySyncFailed = 'repository.sync_failed';
    case PackageCreated = 'package.created';
    case PackageRemoved = 'package.removed';
    case MemberAdded = 'member.added';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
    case InvitationSent = 'invitation.sent';
    case TokenCreated = 'token.created';
    case TokenRevoked = 'token.revoked';

    public function label(): string
    {
        return match ($this) {
            self::RepositoryAdded => 'Repository added',
            self::RepositoryRemoved => 'Repository removed',
            self::RepositorySynced => 'Repository synced',
            self::RepositorySyncFailed => 'Sync failed',
            self::PackageCreated => 'Package created',
            self::PackageRemoved => 'Package removed',
            self::MemberAdded => 'Member joined',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Role changed',
            self::InvitationSent => 'Invitation sent',
            self::TokenCreated => 'Token created',
            self::TokenRevoked => 'Token revoked',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RepositoryAdded => 'git-branch-plus',
            self::RepositoryRemoved => 'git-branch',
            self::RepositorySynced => 'refresh-cw',
            self::RepositorySyncFailed => 'alert-circle',
            self::PackageCreated => 'package-plus',
            self::PackageRemoved => 'package-minus',
            self::MemberAdded => 'user-plus',
            self::MemberRemoved => 'user-minus',
            self::MemberRoleChanged => 'shield',
            self::InvitationSent => 'mail',
            self::TokenCreated => 'key-round',
            self::TokenRevoked => 'key-round',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::RepositoryAdded, self::RepositoryRemoved, self::RepositorySynced, self::RepositorySyncFailed => 'repository',
            self::PackageCreated, self::PackageRemoved => 'package',
            self::MemberAdded, self::MemberRemoved, self::MemberRoleChanged, self::InvitationSent => 'member',
            self::TokenCreated, self::TokenRevoked => 'token',
        };
    }
}
