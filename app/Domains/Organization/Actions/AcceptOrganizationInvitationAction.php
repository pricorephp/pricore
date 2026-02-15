<?php

namespace App\Domains\Organization\Actions;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Str;

class AcceptOrganizationInvitationAction
{
    public function handle(OrganizationInvitation $invitation, User $user): void
    {
        $invitation->organization->members()->attach($user->uuid, [
            'uuid' => Str::uuid()->toString(),
            'role' => $invitation->role->value,
        ]);

        $invitation->update(['accepted_at' => now()]);
    }
}
