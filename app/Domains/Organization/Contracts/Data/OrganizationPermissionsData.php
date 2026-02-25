<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\Organization;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationPermissionsData extends Data
{
    public function __construct(
        public bool $canViewSettings,
        public bool $canManageMembers,
        public bool $canDeleteOrganization,
        public bool $canUpdateSlug,
        public bool $canManageRepository,
    ) {}

    public static function fromUserAndOrganization(User $user, Organization $organization): self
    {
        return new self(
            canViewSettings: $user->can('viewSettings', $organization),
            canManageMembers: $user->can('manageMembers', $organization),
            canDeleteOrganization: $user->can('delete', $organization),
            canUpdateSlug: $user->can('updateSlug', $organization),
            canManageRepository: $user->can('deleteRepository', $organization),
        );
    }
}
