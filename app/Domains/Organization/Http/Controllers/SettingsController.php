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

        $user = auth()->user();
        $isOwner = $user !== null && $organization->owner_uuid === $user->uuid;

        return Inertia::render('organizations/settings/general', [
            'organization' => OrganizationData::from($organization),
            'isOwner' => $isOwner,
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();
        $isOwner = $user !== null && $organization->owner_uuid === $user->uuid;

        $updateData = [
            'name' => $request->validated('name'),
        ];

        if ($isOwner && $request->has('slug')) {
            $updateData['slug'] = $request->validated('slug');
        }

        $organization->update($updateData);
        $organization->refresh();

        return redirect()
            ->route('organizations.settings.general', $organization)
            ->with('success', 'Organization updated successfully.');
    }
}
