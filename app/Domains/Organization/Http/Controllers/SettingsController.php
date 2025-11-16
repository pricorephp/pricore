<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController
{
    use AuthorizesRequests;

    public function index(Organization $organization): RedirectResponse
    {
        return redirect()->route('organizations.settings.general', $organization);
    }

    public function general(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        return Inertia::render('organizations/settings/general', [
            'organization' => OrganizationData::from($organization),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'name' => $request->validated('name'),
        ]);

        return redirect()
            ->route('organizations.settings.general', $organization)
            ->with('success', 'Organization updated successfully.');
    }
}
