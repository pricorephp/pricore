<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationSshKeyData;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Repository\Actions\BulkCreateRepositoriesAction;
use App\Domains\Repository\Actions\DeleteWebhookAction;
use App\Domains\Repository\Actions\ExtractRepositoryNameAction;
use App\Domains\Repository\Actions\RegisterWebhookAction;
use App\Domains\Repository\Contracts\Data\RepositoryData;
use App\Domains\Repository\Contracts\Data\SyncLogData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Http\Requests\BulkStoreRepositoryRequest;
use App\Domains\Repository\Http\Requests\StoreRepositoryRequest;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function __construct(
        protected ExtractRepositoryNameAction $extractRepositoryNameAction,
        protected RegisterWebhookAction $registerWebhookAction,
        protected DeleteWebhookAction $deleteWebhookAction,
        protected RecordActivityTask $recordActivity,
    ) {}

    public function index(Organization $organization): Response
    {
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

    public function store(StoreRepositoryRequest $request, Organization $organization): RedirectResponse
    {
        $name = $request->name ?? $this->extractRepositoryNameAction->handle(
            $request->repo_identifier,
            GitProvider::from($request->provider)
        );

        $provider = GitProvider::from($request->provider);

        /** @var User $user */
        $user = auth()->user();

        $baseUrl = $this->resolveBaseUrl($provider, $user->uuid);

        $isGenericGit = $provider === GitProvider::Git;

        $repository = Repository::create([
            'organization_uuid' => $organization->uuid,
            'credential_user_uuid' => $isGenericGit ? null : $user->uuid,
            'ssh_key_uuid' => $isGenericGit ? $request->ssh_key_uuid : null,
            'name' => $name,
            'provider' => $provider,
            'repo_identifier' => $request->repo_identifier,
            'custom_base_url' => $baseUrl,
            'default_branch' => $request->default_branch,
        ]);

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::RepositoryAdded,
            subject: $repository,
            actor: $request->user(),
            properties: ['name' => $repository->name, 'provider' => $repository->provider->value],
        );

        SyncRepositoryJob::dispatch($repository);

        $webhookRegistered = $this->registerWebhookAction->handle($repository);

        $message = 'Repository added successfully.';
        if (! $webhookRegistered && $repository->provider->supportsAutomaticWebhooks()) {
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
            'canManageRepository' => auth()->user()?->can('deleteRepository', $organization) ?? false,
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
        ]);
    }

    public function update(Organization $organization, Repository $repository): RedirectResponse
    {
        // Placeholder for future updates
        return redirect()
            ->route('organizations.repositories.edit', [$organization, $repository])
            ->with('status', 'Repository updated successfully.');
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

        $repository->delete();

        return redirect()
            ->route('organizations.repositories.index', $organization)
            ->with('status', 'Repository deleted successfully.');
    }

    private function resolveBaseUrl(GitProvider $provider, string $userUuid): ?string
    {
        if (! $provider->supportsSelfHosted()) {
            return null;
        }

        $credential = UserGitCredential::query()
            ->where('user_uuid', $userUuid)
            ->where('provider', $provider)
            ->first();

        return $credential?->credentials['url'] ?? null;
    }
}
