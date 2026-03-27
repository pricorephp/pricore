<?php

namespace App\Domains\Security\Http\Controllers;

use App\Domains\Security\Actions\ScanOrganizationPackagesAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class ScanSecurityController extends Controller
{
    public function __invoke(Organization $organization, ScanOrganizationPackagesAction $scanOrganizationPackagesAction): RedirectResponse
    {
        if (! $organization->security_audits_enabled) {
            return redirect()->back()->with('status', 'Security audits are disabled for this organization.');
        }

        $scanOrganizationPackagesAction->handle($organization);

        return redirect()->back()->with('status', 'Security scan has started.');
    }
}
