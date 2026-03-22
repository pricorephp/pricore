<?php

namespace App\Domains\Mirror\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Mirror\Contracts\Data\MirrorData;
use App\Domains\Mirror\Contracts\Data\MirrorSyncLogData;
use App\Domains\Mirror\Http\Requests\StoreMirrorRequest;
use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MirrorController
{
    use AuthorizesRequests;

    public function __construct(
        protected RecordActivityTask $recordActivityTask,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        $mirrors = $organization->mirrors()
            ->withCount('packages')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Mirror $mirror) => MirrorData::fromModel($mirror));

        return Inertia::render('organizations/settings/mirrors', [
            'organization' => OrganizationData::fromModel($organization),
            'mirrors' => $mirrors,
        ]);
    }

    public function show(Organization $organization, Mirror $mirror): Response
    {
        $this->authorize('viewSettings', $organization);

        $mirror->loadCount('packages');

        $packages = $mirror->packages()
            ->withCount('versions')
            ->orderBy('name')
            ->get()
            ->map(fn ($package) => PackageData::fromModel($package));

        $syncLogs = $mirror->syncLogs()
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($mirrorSyncLog) => MirrorSyncLogData::fromModel($mirrorSyncLog));

        return Inertia::render('organizations/settings/mirrors/show', [
            'organization' => OrganizationData::fromModel($organization),
            'mirror' => MirrorData::fromModel($mirror),
            'packages' => $packages,
            'syncLogs' => $syncLogs,
        ]);
    }

    public function store(StoreMirrorRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $mirror = $organization->mirrors()->create([
            'name' => $request->validated('name'),
            'url' => $request->validated('url'),
            'auth_type' => $request->validated('auth_type'),
            'auth_credentials' => $request->authCredentials(),
            'mirror_dist' => $request->validated('mirror_dist', true),
            'sync_status' => RepositorySyncStatus::Pending,
        ]);

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::MirrorAdded,
            subject: $mirror,
            actor: $request->user(),
            properties: ['name' => $mirror->name],
        );

        SyncMirrorJob::dispatch($mirror);

        return redirect()
            ->route('organizations.settings.mirrors.index', $organization)
            ->with('status', 'Registry mirror added. Sync has started.');
    }

    public function destroy(Request $request, Organization $organization, Mirror $mirror): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::MirrorRemoved,
            subject: $mirror,
            actor: $request->user(),
            properties: ['name' => $mirror->name],
        );

        $mirror->delete();

        return redirect()
            ->route('organizations.settings.mirrors.index', $organization)
            ->with('status', 'Registry mirror removed.');
    }
}
