<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Actions\BulkCreateRepositoriesAction;
use App\Domains\Repository\Actions\CreateRepositoryAction;
use App\Domains\Repository\Actions\DeleteWebhookAction;
use App\Domains\Repository\Actions\PurgeDistArchiveFilesTask;
use App\Domains\Repository\Actions\RequestRepositorySyncAction;
use App\Domains\Repository\Actions\UpdatePackagePathsAction;
use App\Domains\Repository\Contracts\Data\BulkImportResultData;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Http\Requests\BulkStoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\StoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\UpdateRepositoryRequest;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class RepositoryController extends ApiController
{
    public function __construct(
        protected DeleteWebhookAction $deleteWebhook,
        protected RecordActivityTask $recordActivity,
        protected PurgeDistArchiveFilesTask $purgeDistArchiveFilesTask,
    ) {}

    /**
     * @return PaginatedDataCollection<array-key, RepositoryData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('view', $organization);

        $repositories = $organization->repositories()
            ->withCount('packages')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn ($repository) => RepositoryData::fromModel($repository));

        return RepositoryData::collect($repositories, PaginatedDataCollection::class);
    }

    public function store(StoreRepositoryRequest $request, Organization $organization, CreateRepositoryAction $createRepository): RepositoryData
    {
        $this->authorize('deleteRepository', $organization);

        $provider = GitProvider::from($request->validated('provider'));

        if ($provider !== GitProvider::Git) {
            $this->requirePersonalAccessTokenForCredentials($request);
        }

        /** @var User $user */
        $user = $request->user();

        $repository = $createRepository->handle(
            organization: $organization,
            provider: $provider,
            repoIdentifier: $request->validated('repo_identifier'),
            user: $user,
            name: $request->validated('name'),
            defaultBranch: $request->validated('default_branch'),
            sshKeyUuid: $request->validated('ssh_key_uuid'),
            packagePaths: $request->packagePaths(),
        );

        return RepositoryData::fromModel($repository->loadCount('packages'), includeWebhookSecret: true);
    }

    public function bulkStore(BulkStoreRepositoryRequest $request, Organization $organization, BulkCreateRepositoriesAction $bulkCreate): BulkImportResultData
    {
        $this->authorize('deleteRepository', $organization);
        $this->requirePersonalAccessTokenForCredentials($request);

        /** @var User $user */
        $user = $request->user();

        return $bulkCreate->handle(
            organization: $organization,
            provider: GitProvider::from($request->validated('provider')),
            repositories: $request->validated('repositories'),
            userUuid: $user->uuid,
        );
    }

    public function show(Request $request, Organization $organization, Repository $repository): RepositoryData
    {
        $this->authorize('view', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        // Like the repository page: only those who manage repositories see the webhook secret.
        return RepositoryData::fromModel(
            $repository->loadCount('packages'),
            includeWebhookSecret: $request->user()?->can('deleteRepository', $organization) ?? false,
        );
    }

    public function sync(Organization $organization, Repository $repository, RequestRepositorySyncAction $requestRepositorySyncAction): RepositoryData
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        $requestRepositorySyncAction->handle($repository);

        return RepositoryData::fromModel($repository->loadCount('packages'), includeWebhookSecret: true);
    }

    /**
     * Update a repository's monorepo package paths. Omit `package_paths` to leave them
     * as they are, or send `null` to read the package from the repository root. Packages
     * outside the new paths are removed and a full sync is started.
     */
    public function update(UpdateRepositoryRequest $request, Organization $organization, Repository $repository, UpdatePackagePathsAction $updatePackagePathsAction): RepositoryData
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        if ($request->has('package_paths')) {
            $updatePackagePathsAction->handle($repository, $request->packagePaths(), $request->user());
        }

        return RepositoryData::fromModel($repository->refresh()->loadCount('packages'), includeWebhookSecret: true);
    }

    public function destroy(Request $request, Organization $organization, Repository $repository): Response
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::RepositoryRemoved,
            subject: $repository,
            actor: $request->user(),
            properties: ['name' => $repository->name],
        );

        $this->deleteWebhook->handle($repository);

        // Packages, versions and archive rows all cascade at the database level,
        // which fires no model events. Clear the files while the rows still exist.
        $this->purgeDistArchiveFilesTask->handle($repository->packages()->pluck('uuid'));

        $repository->delete();

        return response()->noContent();
    }

    /**
     * Repositories on a Git provider are cloned with the Git credentials of the
     * user adding them. An organization token acts as the owner, so it must not
     * borrow the owner's credentials on behalf of whoever created the token.
     */
    private function requirePersonalAccessTokenForCredentials(Request $request): void
    {
        abort_if(
            $this->accessToken($request)->organization_uuid !== null,
            403,
            'Adding repositories from a Git provider uses your own Git credentials and requires a personal access token.',
        );
    }

    private function ensureBelongsToOrganization(Organization $organization, Repository $repository): void
    {
        abort_unless($repository->organization_uuid === $organization->uuid, 404);
    }
}
