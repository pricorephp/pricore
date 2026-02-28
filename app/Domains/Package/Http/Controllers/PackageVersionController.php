<?php

namespace App\Domains\Package\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageVersionController extends Controller
{
    public function destroy(
        Request $request,
        Organization $organization,
        Package $package,
        PackageVersion $version,
    ): RedirectResponse {
        if ($package->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        if ($version->package_uuid !== $package->uuid) {
            abort(404);
        }

        if (! $request->user()?->can('deleteRepository', $organization)) {
            abort(403);
        }

        $version->delete();

        return redirect()
            ->back()
            ->with('status', 'Version deleted successfully.');
    }
}
