<?php

namespace App\Http\Middleware;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationPermissionsData;
use App\Domains\Search\Contracts\Data\SearchPackageData;
use App\Domains\Search\Contracts\Data\SearchRepositoryData;
use App\Http\Data\AuthData;
use App\Http\Data\FlashData;
use App\Http\Data\SearchData;
use App\Http\Data\UserData;
use App\Models\Package;
use App\Models\Repository;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'version' => config('app.version'),
            'auth' => new AuthData(
                user: $user ? UserData::fromModel($user) : null,
                organizations: $user
                    ? $user->organizations()->get()->map(function ($org) use ($user) {
                        $data = OrganizationData::fromModel($org);
                        $data->permissions = OrganizationPermissionsData::fromUserAndOrganization($user, $org);

                        return $data;
                    })->all()
                    : [],
            ),
            'search' => $user ? fn () => $this->searchData($request) : new SearchData(packages: [], repositories: []),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => new FlashData(
                status: $request->session()->get('status') ?? $request->session()->get('success'),
                error: $request->session()->get('error'),
            ),
        ];
    }

    private function searchData(Request $request): SearchData
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $organizationUuids = $user->organizations()->pluck('organizations.uuid');

        $packages = Package::query()
            ->whereIn('organization_uuid', $organizationUuids)
            ->with('organization:uuid,name,slug')
            ->get()
            ->map(fn (Package $package) => SearchPackageData::fromModel($package))
            ->all();

        $repositories = Repository::query()
            ->whereIn('organization_uuid', $organizationUuids)
            ->with('organization:uuid,name,slug')
            ->get()
            ->map(fn (Repository $repository) => SearchRepositoryData::fromModel($repository))
            ->all();

        return new SearchData(
            packages: $packages,
            repositories: $repositories,
        );
    }
}
