<?php

namespace App\Domains\Organization\Contracts\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Owner->value => self::Owner->label(),
            self::Admin->value => self::Admin->label(),
            self::Member->value => self::Member->label(),
        ];
    }

    /**
     * Roles that can be assigned to members (excludes Owner).
     *
     * @return array<string, string>
     */
    public static function assignableOptions(): array
    {
        return [
            self::Admin->value => self::Admin->label(),
            self::Member->value => self::Member->label(),
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function assignableRoles(): array
    {
        return [self::Admin, self::Member];
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canManageSettings(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
