<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Organization\Actions\ResendOrganizationInvitationAction;
use App\Domains\Organization\Contracts\Data\OrganizationInvitationData;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class InvitationController extends ApiController
{
    /**
     * @return PaginatedDataCollection<array-key, OrganizationInvitationData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('viewSettings', $organization);

        $invitations = $organization->invitations()
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn ($invitation) => OrganizationInvitationData::fromModel($invitation));

        return OrganizationInvitationData::collect($invitations, PaginatedDataCollection::class);
    }

    public function resend(Organization $organization, OrganizationInvitation $invitation, ResendOrganizationInvitationAction $resend): OrganizationInvitationData
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($invitation->organization_uuid === $organization->uuid, 404);
        abort_if($invitation->isAccepted(), 422, 'This invitation has already been accepted.');

        $resend->handle($invitation);

        return OrganizationInvitationData::fromModel($invitation->refresh());
    }

    public function destroy(Organization $organization, OrganizationInvitation $invitation): Response
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($invitation->organization_uuid === $organization->uuid, 404);

        $invitation->delete();

        return response()->noContent();
    }
}
