<?php

namespace App\Listeners;

use App\Domains\Organization\Actions\AcceptOrganizationInvitationAction;
use App\Models\OrganizationInvitation;
use App\Models\User;

class AcceptPendingInvitationListener
{
    public function __construct(
        protected AcceptOrganizationInvitationAction $acceptAction,
    ) {}

    public function handle(object $event): void
    {
        $token = session('invitation_token');

        if (! $token) {
            return;
        }

        $user = $event->user ?? null;

        if (! $user instanceof User) {
            return;
        }

        $invitation = OrganizationInvitation::with('organization')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            session()->forget('invitation_token');

            return;
        }

        if ($invitation->organization->members()->where('user_uuid', $user->uuid)->exists()) {
            $invitation->update(['accepted_at' => now()]);
            session()->forget('invitation_token');

            return;
        }

        $this->acceptAction->handle($invitation, $user);

        session()->forget('invitation_token');
    }
}
