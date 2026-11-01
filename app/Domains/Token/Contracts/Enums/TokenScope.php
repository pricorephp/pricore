<?php

namespace App\Domains\Token\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TokenScope: string
{
    // Installing packages through the Composer registry.
    case Composer = 'composer';

    case ReadOrganizations = 'read:organizations';
    case WriteOrganizations = 'write:organizations';
    case DeleteOrganizations = 'delete:organizations';

    case ReadRepositories = 'read:repositories';
    case WriteRepositories = 'write:repositories';
    case DeleteRepositories = 'delete:repositories';

    case ReadPackages = 'read:packages';
    // Uploading versions of artifact packages through the Composer registry.
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
            self::Composer => 'Install packages',
            self::ReadOrganizations => 'Read organizations',
            self::WriteOrganizations => 'Create & update organizations',
            self::DeleteOrganizations => 'Delete organizations',
            self::ReadRepositories => 'Read repositories',
            self::WriteRepositories => 'Add & sync repositories',
            self::DeleteRepositories => 'Delete repositories',
            self::ReadPackages => 'Read packages',
            self::WritePackages => 'Publish packages',
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
