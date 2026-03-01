<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendOrganizationInvitationAction
{
    public function __construct(
        protected RecordActivityTask $recordActivity,
    ) {}

    public function handle(Organization $organization, string $email, OrganizationRole $role, User $invitedBy): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::create([
            'organization_uuid' => $organization->uuid,
            'email' => $email,
            'role' => $role->value,
            'token' => Str::random(64),
            'invited_by' => $invitedBy->uuid,
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load('organization', 'invitedBy');

        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation));

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::InvitationSent,
            subject: $invitation,
            actor: $invitedBy,
            properties: ['email' => $email, 'role' => $role->value],
        );

        return $invitation;
    }
}
