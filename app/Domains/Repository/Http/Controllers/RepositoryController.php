<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Repository\Actions\ExtractRepositoryNameAction;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Domains\Repository\Contracts\Data\SyncLogData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Http\Requests\StoreRepositoryRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function __construct(
        protected ExtractRepositoryNameAction $extractRepositoryName
    ) {}

    public function index(Organization $organization): Response
    {
        $repositories = $organization->repositories()
            ->withCount('packages')
            ->orderBy('name')
            ->get()
            ->map(fn ($repository) => RepositoryData::fromModel($repository));

        $configuredProviders = $organization->gitCredentials()
            ->pluck('provider')
            ->map(fn (GitProvider $provider) => $provider->value)
            ->toArray();

        return Inertia::render('organizations/repositories', [
            'organization' => OrganizationData::fromModel($organization),
            'repositories' => $repositories,
            'configuredProviders' => $configuredProviders,
        ]);
    }

    public function store(StoreRepositoryRequest $request, Organization $organization): RedirectResponse
    {
        $name = $request->name ?? $this->extractRepositoryName->handle(
            $request->repo_identifier,
            GitProvider::from($request->provider)
        );

        $repository = Repository::create([
            'organization_uuid' => $organization->uuid,
            'name' => $name,
            'provider' => GitProvider::from($request->provider),
            'repo_identifier' => $request->repo_identifier,
            'default_branch' => $request->default_branch,
        ]);

        return redirect()
            ->route('organizations.repositories.index', $organization)
            ->with('success', 'Repository added successfully.');
    }

    public function show(Organization $organization, Repository $repository): Response
    {
        $repository->load('organization');
        $repository->loadCount('packages');

        $packages = $repository->packages()
            ->with('repository')
            ->withCount('versions')
            ->orderBy('name')
            ->get()
            ->map(fn ($package) => PackageData::fromModel($package));

        $syncLogs = $repository->syncLogs()
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($syncLog) => SyncLogData::fromModel($syncLog));

        return Inertia::render('organizations/repositories/show', [
            'organization' => OrganizationData::fromModel($organization),
            'repository' => RepositoryData::fromModel($repository),
            'packages' => $packages,
            'syncLogs' => $syncLogs,
        ]);
    }
}
