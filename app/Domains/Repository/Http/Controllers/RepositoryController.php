<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationSshKeyData;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Repository\Actions\BulkCreateRepositoriesAction;
use App\Domains\Repository\Actions\CreateRepositoryAction;
use App\Domains\Repository\Actions\DeleteWebhookAction;
use App\Domains\Repository\Actions\PurgeDistArchiveFilesTask;
use App\Domains\Repository\Actions\RecordRepositoryViewTask;
use App\Domains\Repository\Actions\UpdatePackagePathsAction;
use App\Domains\Repository\Actions\UpdateTagFormatAction;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Domains\Repository\Contracts\Data\SyncLogData;
use App\Domains\Repository\Contracts\Data\UpdatePackagePathsResultData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Http\Requests\BulkStoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\StoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\UpdateRepositoryRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DeleteWebhookAction $deleteWebhookAction,
        protected RecordActivityTask $recordActivity,
        protected PurgeDistArchiveFilesTask $purgeDistArchiveFilesTask,
        protected RecordRepositoryViewTask $recordRepositoryViewTask,
        protected UpdatePackagePathsAction $updatePackagePathsAction,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $repositories = $organization->repositories()
            ->withCount('packages')
            ->orderBy('name')
            ->get()
            ->map(fn ($repository) => RepositoryData::fromModel($repository));

        /** @var User $user */
        $user = auth()->user();

        $configuredProviders = $user->gitCredentials()
            ->pluck('provider')
            ->map(fn (GitProvider $provider) => $provider->value)
            ->toArray();

        $sshKeys = $organization->sshKeys()
            ->orderBy('name')
            ->get()
            ->map(fn ($organizationSshKey) => OrganizationSshKeyData::fromModel($organizationSshKey));

        return Inertia::render('organizations/repositories', [
            'organization' => OrganizationData::fromModel($organization),
            'repositories' => $repositories,
            'configuredProviders' => $configuredProviders,
            'sshKeys' => $sshKeys,
        ]);
    }

    public function store(StoreRepositoryRequest $request, Organization $organization, CreateRepositoryAction $createRepository): RedirectResponse
    {
        $this->authorize('deleteRepository', $organization);

        /** @var User $user */
        $user = auth()->user();

        $repository = $createRepository->handle(
            organization: $organization,
            provider: GitProvider::from($request->provider),
            repoIdentifier: $request->repo_identifier,
            user: $user,
            name: $request->name,
            defaultBranch: $request->default_branch,
            sshKeyUuid: $request->ssh_key_uuid,
            packagePaths: $request->packagePaths(),
        );

        $message = 'Repository added successfully.';
        if ($repository->webhook_id === null && $repository->provider->supportsAutomaticWebhooks()) {
            $message .= ' Webhook registration failed — you can retry from the repository page.';
        }

        return redirect()
            ->route('organizations.repositories.index', $organization)
            ->with('status', $message);
    }

    public function bulkStore(
        BulkStoreRepositoryRequest $request,
        Organization $organization,
        BulkCreateRepositoriesAction $bulkCreateAction,
    ): RedirectResponse {
        $this->authorize('deleteRepository', $organization);

        /** @var User $user */
        $user = $request->user();

        $result = $bulkCreateAction->handle(
            organization: $organization,
            provider: GitProvider::from($request->validated('provider')),
            repositories: $request->validated('repositories'),
            userUuid: $user->uuid,
        );

        return redirect()
            ->route('organizations.repositories.index', $organization)
            ->with('status', $result->statusMessage());
    }

    public function show(Organization $organization, Repository $repository): Response
    {
        $this->authorize('view', $organization);

        /** @var User|null $user */
        $user = auth()->user();

        if ($user) {
            $this->recordRepositoryViewTask->handle($user, $repository);
        }

        $canManageRepository = $user?->can('deleteRepository', $organization) ?? false;

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
            'repository' => RepositoryData::fromModel($repository, includeWebhookSecret: $canManageRepository),
            'packages' => $packages,
            'syncLogs' => $syncLogs,
            'canManageRepository' => $canManageRepository,
        ]);
    }

    public function edit(Organization $organization, Repository $repository): Response
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->can('deleteRepository', $organization)) {
            abort(403);
        }

        return Inertia::render('organizations/repositories/edit', [
            'organization' => OrganizationData::fromModel($organization),
            'repository' => RepositoryData::fromModel($repository),
            'distEnabled' => (bool) config('pricore.dist.enabled'),
        ]);
    }

    public function update(UpdateRepositoryRequest $request, Organization $organization, Repository $repository, UpdateTagFormatAction $updateTagFormatAction): RedirectResponse
    {
        if ($repository->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $this->authorize('deleteRepository', $organization);

        // Absent means "leave as is"; an explicit empty value clears the paths
        $result = $request->has('package_paths')
            ? $this->updatePackagePathsAction->handle($repository, $request->packagePaths(), $request->user())
            : new UpdatePackagePathsResultData(changed: false, packagesRemoved: 0);

        $tagFormatChanged = $request->has('tag_format')
            && $updateTagFormatAction->handle($repository, $request->validated('tag_format'));

        $message = match (true) {
            $result->changed && $tagFormatChanged => 'Package paths and tag format updated and a sync has been started.',
            $result->changed => 'Package paths updated and a sync has been started.',
            $tagFormatChanged => 'Tag format updated and a sync has been started.',
            default => 'Repository updated successfully.',
        };

        if ($result->packagesRemoved > 0) {
            $message .= sprintf(
                ' Removed %d package%s outside the configured paths.',
                $result->packagesRemoved,
                $result->packagesRemoved === 1 ? '' : 's',
            );
        }

        return redirect()
            ->route('organizations.repositories.edit', [$organization, $repository])
            ->with('status', $message);
    }

    public function destroy(Request $request, Organization $organization, Repository $repository): RedirectResponse
    {
        if ($repository->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        if (! $request->user()?->can('deleteRepository', $organization)) {
            abort(403);
        }

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::RepositoryRemoved,
            subject: $repository,
            actor: $request->user(),
            properties: ['name' => $repository->name],
        );

        $this->deleteWebhookAction->handle($repository);

        // Packages, versions and archive rows all cascade at the database level,
        // which fires no model events. Clear the files while the rows still exist.
        $this->purgeDistArchiveFilesTask->handle($repository->packages()->pluck('uuid'));

        $repository->delete();

        return redirect()
            ->route('organizations.repositories.index', $organization)
            ->with('status', 'Repository deleted successfully.');
    }
}
