<?php

namespace App\Domains\Token\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TokenScope: string
{
    // Composer registry access (what legacy and org tokens do today).
    case Composer = 'composer';

    case ReadOrganizations = 'read:organizations';
    case WriteOrganizations = 'write:organizations';
    case DeleteOrganizations = 'delete:organizations';

    case ReadRepositories = 'read:repositories';
    case WriteRepositories = 'write:repositories';
    case DeleteRepositories = 'delete:repositories';

    case ReadPackages = 'read:packages';
    case WritePackages = 'write:packages';
    case DeletePackages = 'delete:packages';

    case ReadMembers = 'read:members';
    case WriteMembers = 'write:members';

    case ReadMirrors = 'read:mirrors';
    case WriteMirrors = 'write:mirrors';
    case DeleteMirrors = 'delete:mirrors';

    case ReadTokens = 'read:tokens';
    case WriteTokens = 'write:tokens';

    public function label(): string
    {
        return match ($this) {
            self::Composer => 'Composer registry access',
            self::ReadOrganizations => 'Read organizations',
            self::WriteOrganizations => 'Create & update organizations',
            self::DeleteOrganizations => 'Delete organizations',
            self::ReadRepositories => 'Read repositories',
            self::WriteRepositories => 'Add & sync repositories',
            self::DeleteRepositories => 'Delete repositories',
            self::ReadPackages => 'Read packages',
            self::WritePackages => 'Manage packages',
            self::DeletePackages => 'Delete packages & versions',
            self::ReadMembers => 'Read members & invitations',
            self::WriteMembers => 'Manage members & invitations',
            self::ReadMirrors => 'Read mirrors',
            self::WriteMirrors => 'Add & sync mirrors',
            self::DeleteMirrors => 'Delete mirrors',
            self::ReadTokens => 'Read access tokens',
            self::WriteTokens => 'Create & revoke access tokens',
        };
    }

    /**
     * The resource group this scope belongs to (used to group scopes in the UI).
     */
    public function resource(): string
    {
        return match ($this) {
            self::Composer => 'composer',
            self::ReadOrganizations, self::WriteOrganizations, self::DeleteOrganizations => 'organizations',
            self::ReadRepositories, self::WriteRepositories, self::DeleteRepositories => 'repositories',
            self::ReadPackages, self::WritePackages, self::DeletePackages => 'packages',
            self::ReadMembers, self::WriteMembers => 'members',
            self::ReadMirrors, self::WriteMirrors, self::DeleteMirrors => 'mirrors',
            self::ReadTokens, self::WriteTokens => 'tokens',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $scope) {
            $options[$scope->value] = $scope->label();
        }

        return $options;
    }

    /**
     * Scopes grouped by resource for grouped UI rendering.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $scope) {
            $grouped[$scope->resource()][$scope->value] = $scope->label();
        }

        return $grouped;
    }

    /**
     * Default scopes for a token used purely for Composer registry access.
     *
     * @return array<int, self>
     */
    public static function composerDefault(): array
    {
        return [self::Composer];
    }

    /**
     * Normalize a mixed list of scopes into a deduplicated list of string values.
     *
     * @param  array<int, self|string>  $scopes
     * @return array<int, string>
     */
    public static function normalize(array $scopes): array
    {
        return array_values(array_unique(array_map(
            fn (self|string $scope) => $scope instanceof self ? $scope->value : $scope,
            $scopes,
        )));
    }
}
