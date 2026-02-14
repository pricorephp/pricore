<?php

namespace App\Policies;

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

        return $pivot->role->canManageSettings();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewSettings($user, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->viewSettings($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $organization->owner_uuid === $user->uuid;
    }

    public function updateSlug(User $user, Organization $organization): bool
    {
        return $organization->owner_uuid === $user->uuid;
    }

    public function deleteRepository(User $user, Organization $organization): bool
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

        return $pivot->role->isAdmin();
    }
}
