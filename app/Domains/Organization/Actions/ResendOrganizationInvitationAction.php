<?php

namespace App\Domains\Organization\Actions;

use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

class ResendOrganizationInvitationAction
{
    public function handle(OrganizationInvitation $invitation): void
    {
        $invitation->update([
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load('organization', 'invitedBy');

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));
    }
}
