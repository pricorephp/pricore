<?php

namespace App\Domains\Security\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecuritySettingsController extends Controller
{
    use AuthorizesRequests;

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        return Inertia::render('organizations/settings/security', [
            'organization' => OrganizationData::fromModel($organization),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $validated = $request->validate([
            'security_audits_enabled' => ['required', 'boolean'],
            'security_notifications_enabled' => ['required', 'boolean'],
        ]);

        $organization->update($validated);

        return redirect()->back()->with('status', 'Security settings updated.');
    }
}
