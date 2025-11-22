<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Pivots\OrganizationUserPivot;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationWithRoleData extends Data
{
    public function __construct(
        public OrganizationData $organization,
        public OrganizationRole $role,
        public bool $isOwner,
        public string $pivotUuid,
    ) {}

    public static function fromOrganizationAndPivot(
        Organization $organization,
        OrganizationUserPivot $pivot,
        User $user
    ): self {
        return new self(
            organization: OrganizationData::fromModel($organization),
            role: $pivot->role,
            isOwner: $organization->owner_uuid === $user->uuid,
            pivotUuid: $pivot->uuid,
        );
    }
}
