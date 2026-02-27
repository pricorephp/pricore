<?php

namespace App\Domains\Package\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Actions\BuildPackageDownloadStatsAction;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function __construct(
        protected BuildPackageDownloadStatsAction $downloadStats,
    ) {}

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

    public function show(Organization $organization, Package $package): Response
    {
        $package->load('organization', 'repository');

        $versions = $package->versions()
            ->orderBySemanticVersion('desc')
            ->paginate(15)
            ->through(fn ($version) => PackageVersionData::fromModel(
                $version,
                $package->repository?->provider,
                $package->repository?->repo_identifier
            ));

        $composerRepositoryUrl = url("/{$organization->slug}/packages.json");

        return Inertia::render('organizations/packages/show', [
            'organization' => OrganizationData::fromModel($organization),
            'package' => PackageData::fromModel($package),
            'versions' => $versions,
            'composerRepositoryUrl' => $composerRepositoryUrl,
            'downloadStats' => $this->downloadStats->handle($package),
            'canManageVersions' => request()->user()?->can('deleteRepository', $organization) ?? false,
        ]);
    }
}
