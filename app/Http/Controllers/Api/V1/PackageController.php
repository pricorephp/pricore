<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Package\Contracts\Data\PackageData;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class PackageController extends ApiController
{
    /**
     * @return PaginatedDataCollection<array-key, PackageData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('view', $organization);

        $packages = $organization->packages()
            ->with(['repository', 'mirror'])
            ->withCount('versions')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn ($package) => PackageData::fromModel($package));

        return PackageData::collect($packages, PaginatedDataCollection::class);
    }

    public function show(Organization $organization, Package $package): PackageData
    {
        $this->authorize('view', $organization);
        $this->ensureBelongsToOrganization($organization, $package);

        $package->load(['repository', 'mirror'])->loadCount('versions');

        return PackageData::fromModel($package);
    }

    public function destroy(Request $request, Organization $organization, Package $package, RecordActivityTask $recordActivity): Response
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensureBelongsToOrganization($organization, $package);

        $recordActivity->handle(
            organization: $organization,
            type: ActivityType::PackageRemoved,
            subject: $package,
            actor: $request->user(),
            properties: ['name' => $package->name],
        );

        // Delete versions through Eloquent so dist files are cleaned up.
        $package->versions()->each(fn ($version) => $version->delete());

        $package->delete();

        return response()->noContent();
    }

    private function ensureBelongsToOrganization(Organization $organization, Package $package): void
    {
        abort_unless($package->organization_uuid === $organization->uuid, 404);
    }
}
