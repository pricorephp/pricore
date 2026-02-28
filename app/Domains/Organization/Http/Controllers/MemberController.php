<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Actions\SendOrganizationInvitationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationInvitationData;
use App\Domains\Organization\Contracts\Data\OrganizationMemberData;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Organization\Requests\AddMemberRequest;
use App\Domains\Organization\Requests\UpdateMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Pivots\OrganizationUserPivot;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController
{
    use AuthorizesRequests;

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        $members = $organization->members()
            ->withPivot('role', 'created_at', 'uuid')
            ->get()
            ->map(function (User $user) {
                $pivot = $user->pivot;

                if (! $pivot instanceof OrganizationUserPivot) {
                    throw new \RuntimeException('Pivot is not an instance of OrganizationUserPivot');
                }

                return OrganizationMemberData::fromUserAndPivot($user, $pivot);
            });

        $invitations = $organization->pendingInvitations()
            ->with('invitedBy')
            ->latest()
            ->get()
            ->map(fn ($invitation) => OrganizationInvitationData::fromModel($invitation));

        return Inertia::render('organizations/settings/members', [
            'organization' => OrganizationData::from($organization),
            'members' => $members,
            'invitations' => $invitations,
            'roleOptions' => OrganizationRole::options(),
        ]);
    }

    public function store(AddMemberRequest $request, Organization $organization, SendOrganizationInvitationAction $sendInvitation): RedirectResponse
    {
        $email = $request->validated('email');
        $role = OrganizationRole::from($request->validated('role'));

        // Check if email belongs to an existing member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $organization->members()->where('user_uuid', $existingUser->uuid)->exists()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'User is already a member of this organization.');
        }

        // Check for existing pending invitation
        if ($organization->pendingInvitations()->where('email', $email)->exists()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'An invitation has already been sent to this email address.');
        }

        /** @var User $user */
        $user = $request->user();

        $sendInvitation->handle($organization, $email, $role, $user);

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('status', "Invitation sent to {$email}.");
    }

    public function update(UpdateMemberRoleRequest $request, Organization $organization, OrganizationUser $member, RecordActivityTask $recordActivity): RedirectResponse
    {
        if ($member->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        if ($member->role->isOwner()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'Cannot change the role of the organization owner.');
        }

        $oldRole = $member->role->value;

        $member->update([
            'role' => $request->validated('role'),
        ]);

        $memberUser = $member->user;
        $recordActivity->handle(
            organization: $organization,
            type: ActivityType::MemberRoleChanged,
            subject: $memberUser,
            actor: $request->user(),
            properties: [
                'member_name' => $memberUser->name,
                'old_role' => $oldRole,
                'new_role' => $request->validated('role'),
            ],
        );

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('status', 'Member role updated successfully.');
    }

    public function destroy(Organization $organization, OrganizationUser $member, RecordActivityTask $recordActivity): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        if ($member->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        if ($member->role->isOwner()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'Cannot remove the organization owner.');
        }

        $memberUser = $member->user;

        $recordActivity->handle(
            organization: $organization,
            type: ActivityType::MemberRemoved,
            subject: $memberUser,
            actor: auth()->user(),
            properties: [
                'member_name' => $memberUser->name,
                'member_email' => $memberUser->email,
            ],
        );

        $member->delete();

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('status', 'Member removed successfully.');
    }
}
