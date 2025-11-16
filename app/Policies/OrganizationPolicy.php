<?php

namespace App\Policies;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewSettings(User $user, Organization $organization): bool
    {
        $member = $organization->members()->where('user_uuid', $user->uuid)->first();

        if (! $member) {
            return false;
        }

        $role = OrganizationRole::from($member->pivot->role);

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
}
