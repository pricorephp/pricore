<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Activity\Contracts\Data\ActivityLogData;
use App\Domains\Organization\Actions\BuildOnboardingChecklistAction;
use App\Domains\Organization\Actions\BuildOrganizationStatsAction;
use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Requests\StoreOrganizationRequest;
use App\Domains\Package\Contracts\Data\FrequentPackageData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CreateOrganizationAction $createOrganization,
        protected BuildOrganizationStatsAction $buildStats,
        protected BuildOnboardingChecklistAction $buildOnboarding,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect('/');
    }

    public function show(Organization $organization): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $configuredProviders = $user->gitCredentials()
            ->pluck('provider')
            ->map(fn (GitProvider $provider) => $provider->value)
            ->toArray();

        return Inertia::render('organizations/show', [
            'organization' => OrganizationData::fromModel($organization),
            'stats' => $this->buildStats->handle($organization),
            'onboarding' => $this->buildOnboarding->handle($organization, $user),
            'configuredProviders' => $configuredProviders,
            'activityLogs' => Inertia::defer(fn () => $organization->activityLogs()
                ->with('actor')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($log) => ActivityLogData::fromModel($log))
            ),
            'frequentPackages' => Inertia::defer(fn () => $organization->packages()
                ->leftJoin('package_views', function ($join) use ($user) {
                    $join->on('packages.uuid', '=', 'package_views.package_uuid')
                        ->where('package_views.user_uuid', $user->uuid);
                })
                ->orderByDesc('package_views.view_count')
                ->orderBy('packages.name')
                ->select('packages.*', DB::raw('COALESCE(package_views.view_count, 0) as user_view_count'))
                ->get()
                ->map(fn ($p) => FrequentPackageData::fromModel($p))
            ),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            abort(401);
        }

        $organization = $this->createOrganization->handle(
            name: $request->validated('name'),
            ownerUuid: $user->uuid
        );

        return redirect()->route('organizations.show', $organization->slug)
            ->with('status', 'Organization created successfully.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('dashboard')
            ->with('status', 'Organization deleted successfully.');
    }
}
