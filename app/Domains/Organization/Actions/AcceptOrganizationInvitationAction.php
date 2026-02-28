<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Str;

class AcceptOrganizationInvitationAction
{
    public function __construct(
        protected RecordActivityTask $recordActivity,
    ) {}

    public function handle(OrganizationInvitation $invitation, User $user): void
    {
        $invitation->organization->members()->attach($user->uuid, [
            'uuid' => Str::uuid()->toString(),
            'role' => $invitation->role->value,
        ]);

        $invitation->update(['accepted_at' => now()]);

        $this->recordActivity->handle(
            organization: $invitation->organization,
            type: ActivityType::MemberAdded,
            subject: $user,
            properties: [
                'member_name' => $user->name,
                'member_email' => $user->email,
                'role' => $invitation->role->value,
            ],
        );
    }
}
