<?php

namespace App\Domains\Package\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Actions\BuildPackageDownloadStatsAction;
use App\Domains\Package\Actions\RecordPackageViewTask;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Domains\Package\Contracts\Data\PackageVersionDetailData;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BuildPackageDownloadStatsAction $downloadStats,
        protected RecordPackageViewTask $recordPackageView,
        protected RecordActivityTask $recordActivityTask,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $packages = $organization->packages()
            ->with(['repository', 'mirror'])
            ->withCount('versions')
            ->orderBy('name')
            ->get()
            ->map(fn ($package) => PackageData::fromModel($package));

        return Inertia::render('organizations/packages', [
            'organization' => OrganizationData::fromModel($organization),
            'packages' => $packages,
        ]);
    }

    public function destroy(Request $request, Organization $organization, Package $package): RedirectResponse
    {
        if ($package->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $this->authorize('deleteRepository', $organization);

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::PackageRemoved,
            subject: $package,
            actor: $request->user(),
            properties: ['name' => $package->name],
        );

        // Delete versions through Eloquent to trigger dist file cleanup
        $package->versions()->each(fn ($version) => $version->delete());

        $package->delete();

        return redirect()
            ->route('organizations.packages.index', $organization)
            ->with('status', 'Package deleted successfully.');
    }

    public function show(Request $request, Organization $organization, Package $package): Response
    {
        $this->authorize('view', $organization);

        if ($request->user() && ! $request->hasAny(['query', 'type', 'page', 'version'])) {
            $this->recordPackageView->handle($request->user(), $package);
        }

        $package->load('organization', 'repository', 'mirror');

        $query = $request->query('query', '');
        $type = $request->query('type', '');

        $versionsQuery = $package->versions()
            ->with('advisoryMatches.advisory')
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

        $versionUuid = $request->query('version');
        $activeVersion = null;

        if ($versionUuid) {
            $versionModel = $package->versions()
                ->with('advisoryMatches.advisory')
                ->where('uuid', $versionUuid)
                ->first();

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
            'downloadStats' => $this->downloadStats->handle($package),
            'canManageVersions' => request()->user()?->can('deleteRepository', $organization) ?? false,
            'canDeletePackage' => request()->user()?->can('deleteRepository', $organization) ?? false,
            'activeVersion' => $activeVersion,
        ]);
    }
}
