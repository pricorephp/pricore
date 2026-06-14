<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Repository\Actions\BulkCreateRepositoriesAction;
use App\Domains\Repository\Actions\CreateRepositoryAction;
use App\Domains\Repository\Actions\DeleteWebhookAction;
use App\Domains\Repository\Contracts\Data\BulkImportResultData;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Http\Requests\BulkStoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\StoreRepositoryRequest;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
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

        /** @var User $user */
        $user = $request->user();

        $repository = $createRepository->handle(
            organization: $organization,
            provider: GitProvider::from($request->validated('provider')),
            repoIdentifier: $request->validated('repo_identifier'),
            user: $user,
            name: $request->validated('name'),
            defaultBranch: $request->validated('default_branch'),
            sshKeyUuid: $request->validated('ssh_key_uuid'),
        );

        return RepositoryData::fromModel($repository->loadCount('packages'));
    }

    public function bulkStore(BulkStoreRepositoryRequest $request, Organization $organization, BulkCreateRepositoriesAction $bulkCreate): BulkImportResultData
    {
        $this->authorize('deleteRepository', $organization);

        /** @var User $user */
        $user = $request->user();

        return $bulkCreate->handle(
            organization: $organization,
            provider: GitProvider::from($request->validated('provider')),
            repositories: $request->validated('repositories'),
            userUuid: $user->uuid,
        );
    }

    public function show(Organization $organization, Repository $repository): RepositoryData
    {
        $this->authorize('view', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        return RepositoryData::fromModel($repository->loadCount('packages'));
    }

    public function sync(Organization $organization, Repository $repository): RepositoryData
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensureBelongsToOrganization($organization, $repository);

        $repository->update(['sync_status' => RepositorySyncStatus::Pending]);

        SyncRepositoryJob::dispatch($repository);

        return RepositoryData::fromModel($repository->loadCount('packages'));
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

        $repository->delete();

        return response()->noContent();
    }

    private function ensureBelongsToOrganization(Organization $organization, Repository $repository): void
    {
        abort_unless($repository->organization_uuid === $organization->uuid, 404);
    }
}
