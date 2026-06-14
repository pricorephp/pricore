<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class PackageVersionController extends ApiController
{
    /**
     * @return PaginatedDataCollection<array-key, PackageVersionData>
     */
    public function index(Request $request, Organization $organization, Package $package): PaginatedDataCollection
    {
        $this->authorize('view', $organization);
        $this->ensurePackageBelongsToOrganization($organization, $package);

        $provider = $package->repository?->provider;
        $repoIdentifier = $package->repository?->repo_identifier;

        $versions = $package->versions()
            ->orderBySemanticVersion('desc')
            ->paginate($this->perPage($request))
            ->through(fn ($version) => PackageVersionData::fromModel($version, $provider, $repoIdentifier));

        return PackageVersionData::collect($versions, PaginatedDataCollection::class);
    }

    public function destroy(Organization $organization, Package $package, PackageVersion $version): Response
    {
        $this->authorize('deleteRepository', $organization);
        $this->ensurePackageBelongsToOrganization($organization, $package);

        abort_unless($version->package_uuid === $package->uuid, 404);

        // Triggers dist file cleanup via the model's deleting event.
        $version->delete();

        return response()->noContent();
    }

    private function ensurePackageBelongsToOrganization(Organization $organization, Package $package): void
    {
        abort_unless($package->organization_uuid === $organization->uuid, 404);
    }
}
