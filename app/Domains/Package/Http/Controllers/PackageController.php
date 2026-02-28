<?php

namespace App\Domains\Package\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Actions\BuildPackageDownloadStatsAction;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Domains\Package\Contracts\Data\PackageVersionDetailData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Http\Request;
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

    public function show(Request $request, Organization $organization, Package $package): Response
    {
        $package->load('organization', 'repository');

        $query = $request->query('query', '');
        $type = $request->query('type', '');

        $versionsQuery = $package->versions()
            ->when($query, fn ($q) => $q->where(function ($q) use ($query) {
                $q->whereLike('version', "%{$query}%")
                    ->orWhereLike('source_reference', "%{$query}%");
            }))
            ->when($type === 'stable', fn ($q) => $q->stable())
            ->when($type === 'dev', fn ($q) => $q->dev())
            ->orderBySemanticVersion('desc');

        $versions = $versionsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($version) => PackageVersionData::fromModel(
                $version,
                $package->repository?->provider,
                $package->repository?->repo_identifier
            ));

        $composerRepositoryUrl = url("/{$organization->slug}/packages.json");

        $versionUuid = $request->query('version');
        $activeVersion = null;

        if ($versionUuid) {
            $versionModel = $package->versions()->where('uuid', $versionUuid)->first();

            if ($versionModel) {
                $activeVersion = PackageVersionDetailData::fromModel(
                    $versionModel,
                    $package->repository?->provider,
                    $package->repository?->repo_identifier,
                );
            }
        }

        return Inertia::render('organizations/packages/show', [
            'organization' => OrganizationData::fromModel($organization),
            'package' => PackageData::fromModel($package),
            'versions' => $versions,
            'filters' => [
                'query' => $query,
                'type' => $type,
            ],
            'composerRepositoryUrl' => $composerRepositoryUrl,
            'downloadStats' => $this->downloadStats->handle($package),
            'canManageVersions' => request()->user()?->can('deleteRepository', $organization) ?? false,
            'activeVersion' => $activeVersion,
        ]);
    }
}
