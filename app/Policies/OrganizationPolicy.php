<?php

namespace App\Policies;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Pivots\OrganizationUserPivot;
use App\Models\User;

class OrganizationPolicy
{
    public function viewSettings(User $user, Organization $organization): bool
    {
        /** @var User|null $member */
        $member = $organization->members()->where('user_uuid', $user->uuid)->first();

        if (! $member) {
            return false;
        }

        $pivot = $member->pivot;

        if (! $pivot instanceof OrganizationUserPivot) {
            return false;
        }

        $role = OrganizationRole::from($pivot->role);

        return $role->canManageSettings();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewSettings($user, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->viewSettings($user, $organization);
    }

    public function updateSlug(User $user, Organization $organization): bool
    {
        return $organization->owner_uuid === $user->uuid;
    }
}
