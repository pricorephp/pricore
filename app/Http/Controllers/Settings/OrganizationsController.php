<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Organization\Contracts\Data\OrganizationWithRoleData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Pivots\OrganizationUserPivot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $organizations = $user
            ->organizations()
            ->withPivot('role', 'uuid')
            ->get()
            ->map(function (Organization $org) use ($user) {
                $pivot = $org->pivot;

                if (! $pivot instanceof OrganizationUserPivot) {
                    throw new \RuntimeException('Pivot is not an instance of OrganizationUserPivot');
                }

                return OrganizationWithRoleData::fromOrganizationAndPivot($org, $pivot, $user);
            });

        return Inertia::render('settings/organizations', [
            'organizations' => $organizations,
        ]);
    }
}
