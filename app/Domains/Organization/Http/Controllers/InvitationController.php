<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Actions\ResendOrganizationInvitationAction;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class InvitationController
{
    use AuthorizesRequests;

    public function destroy(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        if ($invitation->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $invitation->delete();

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('status', 'Invitation cancelled.');
    }

    public function resend(Organization $organization, OrganizationInvitation $invitation, ResendOrganizationInvitationAction $resendAction): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        if ($invitation->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        if ($invitation->isAccepted()) {
            return redirect()
                ->route('organizations.settings.members', $organization)
                ->with('error', 'This invitation has already been accepted.');
        }

        $resendAction->handle($invitation);

        return redirect()
            ->route('organizations.settings.members', $organization)
            ->with('status', 'Invitation resent.');
    }
}
