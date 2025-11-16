<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
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

        return Inertia::render('organizations/settings/members', [
            'organization' => OrganizationData::from($organization),
            'members' => $members,
            'roleOptions' => OrganizationRole::options(),
        ]);
    }

    public function store(AddMemberRequest $request, Organization $organization): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        if ($organization->members()->where('user_uuid', $user->uuid)->exists()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'User is already a member of this organization.');
        }

        $organization->members()->attach($user->uuid, [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'role' => $request->validated('role'),
        ]);

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('success', 'Member added successfully.');
    }

    public function update(UpdateMemberRoleRequest $request, Organization $organization, OrganizationUser $member): RedirectResponse
    {
        if ($member->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $role = OrganizationRole::from($member->role);

        if ($role->isOwner()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'Cannot change the role of the organization owner.');
        }

        $member->update([
            'role' => $request->validated('role'),
        ]);

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('success', 'Member role updated successfully.');
    }

    public function destroy(Organization $organization, OrganizationUser $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        if ($member->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $role = OrganizationRole::from($member->role);

        if ($role->isOwner()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'Cannot remove the organization owner.');
        }

        $member->delete();

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('success', 'Member removed successfully.');
    }
}
