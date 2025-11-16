<?php

namespace App\Domains\Package\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Organization $organization): Response
    {
        $packages = $organization->packages()
            ->with('repository')
            ->withCount('versions')
            ->orderBy('name')
            ->get()
            ->map(fn ($package) => PackageData::fromModel($package));

        return Inertia::render('organizations/packages', [
            'organization' => OrganizationData::fromModel($organization),
            'packages' => $packages,
        ]);
    }
}
