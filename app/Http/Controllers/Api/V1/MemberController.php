<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Actions\SendOrganizationInvitationAction;
use App\Domains\Organization\Contracts\Data\OrganizationInvitationData;
use App\Domains\Organization\Contracts\Data\OrganizationMemberData;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Organization\Requests\AddMemberRequest;
use App\Domains\Organization\Requests\UpdateMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class MemberController extends ApiController
{
    /**
     * @return PaginatedDataCollection<array-key, OrganizationMemberData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('viewSettings', $organization);

        $members = $organization->members()
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (User $user) => OrganizationMemberData::fromUserAndPivot($user, $user->pivot));

        return OrganizationMemberData::collect($members, PaginatedDataCollection::class);
    }

    public function store(AddMemberRequest $request, Organization $organization, SendOrganizationInvitationAction $sendInvitation): OrganizationInvitationData
    {
        $this->authorize('manageMembers', $organization);

        $email = $request->validated('email');
        $role = OrganizationRole::from($request->validated('role'));

        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $organization->members()->where('user_uuid', $existingUser->uuid)->exists()) {
            abort(422, 'User is already a member of this organization.');
        }

        if ($organization->pendingInvitations()->where('email', $email)->exists()) {
            abort(422, 'An invitation has already been sent to this email address.');
        }

        /** @var User $invitedBy */
        $invitedBy = $request->user();

        $invitation = $sendInvitation->handle($organization, $email, $role, $invitedBy);

        return OrganizationInvitationData::fromModel($invitation);
    }

    public function update(UpdateMemberRoleRequest $request, Organization $organization, OrganizationUser $member, RecordActivityTask $recordActivity): OrganizationMemberData
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($member->organization_uuid === $organization->uuid, 404);
        abort_if($member->role->isOwner(), 422, 'Cannot change the role of the organization owner.');

        $oldRole = $member->role->value;
        $member->update(['role' => $request->validated('role')]);

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

        return new OrganizationMemberData(
            uuid: $member->uuid,
            name: $memberUser->name,
            email: $memberUser->email,
            avatar: $memberUser->avatar,
            role: $member->role,
            joinedAt: $member->created_at,
        );
    }

    public function destroy(Request $request, Organization $organization, OrganizationUser $member, RecordActivityTask $recordActivity): Response
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($member->organization_uuid === $organization->uuid, 404);
        abort_if($member->role->isOwner(), 422, 'Cannot remove the organization owner.');

        $memberUser = $member->user;

        $recordActivity->handle(
            organization: $organization,
            type: ActivityType::MemberRemoved,
            subject: $memberUser,
            actor: $request->user(),
            properties: [
                'member_name' => $memberUser->name,
                'member_email' => $memberUser->email,
            ],
        );

        $member->delete();

        return response()->noContent();
    }
}
