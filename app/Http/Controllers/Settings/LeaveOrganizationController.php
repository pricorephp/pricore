<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveOrganizationController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($organization->owner_uuid === $user->uuid) {
            return redirect()
                ->route('settings.organizations')
                ->with('error', 'You cannot leave an organization you own.');
        }

        $organizationUser = OrganizationUser::where('organization_uuid', $organization->uuid)
            ->where('user_uuid', $user->uuid)
            ->first();

        if ($organizationUser === null) {
            return redirect()
                ->route('settings.organizations')
                ->with('error', 'You are not a member of this organization.');
        }

        $organizationUser->delete();

        return redirect()
            ->route('settings.organizations')
            ->with('success', 'You have left the organization successfully.');
    }
}
