<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Mirror\Contracts\Data\MirrorData;
use App\Domains\Mirror\Http\Requests\StoreMirrorRequest;
use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class MirrorController extends ApiController
{
    public function __construct(
        protected RecordActivityTask $recordActivity,
    ) {}

    /**
     * @return PaginatedDataCollection<array-key, MirrorData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('viewSettings', $organization);

        $mirrors = $organization->mirrors()
            ->withCount('packages')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn ($mirror) => MirrorData::fromModel($mirror));

        return MirrorData::collect($mirrors, PaginatedDataCollection::class);
    }

    public function store(StoreMirrorRequest $request, Organization $organization): MirrorData
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

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::MirrorAdded,
            subject: $mirror,
            actor: $request->user(),
            properties: ['name' => $mirror->name],
        );

        SyncMirrorJob::dispatch($mirror);

        return MirrorData::fromModel($mirror->loadCount('packages'));
    }

    public function show(Organization $organization, Mirror $mirror): MirrorData
    {
        $this->authorize('viewSettings', $organization);
        $this->ensureBelongsToOrganization($organization, $mirror);

        return MirrorData::fromModel($mirror->loadCount('packages'));
    }

    public function sync(Organization $organization, Mirror $mirror): MirrorData
    {
        $this->authorize('viewSettings', $organization);
        $this->ensureBelongsToOrganization($organization, $mirror);

        $mirror->update(['sync_status' => RepositorySyncStatus::Pending]);

        SyncMirrorJob::dispatch($mirror);

        return MirrorData::fromModel($mirror->loadCount('packages'));
    }

    public function destroy(Request $request, Organization $organization, Mirror $mirror): Response
    {
        $this->authorize('viewSettings', $organization);
        $this->ensureBelongsToOrganization($organization, $mirror);

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::MirrorRemoved,
            subject: $mirror,
            actor: $request->user(),
            properties: ['name' => $mirror->name],
        );

        $mirror->delete();

        return response()->noContent();
    }

    private function ensureBelongsToOrganization(Organization $organization, Mirror $mirror): void
    {
        abort_unless($mirror->organization_uuid === $organization->uuid, 404);
    }
}
