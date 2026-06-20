<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Requests\StoreOrganizationRequest;
use App\Domains\Organization\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class OrganizationController extends ApiController
{
    /**
     * List organizations accessible to the authenticated token.
     *
     * @return PaginatedDataCollection<array-key, OrganizationData>
     */
    public function index(Request $request): PaginatedDataCollection
    {
        $accessToken = $this->accessToken($request);

        if ($accessToken->organization_uuid !== null) {
            // An organization-scoped token can only ever see its own organization.
            $query = Organization::query()->whereKey($accessToken->organization_uuid);
        } else {
            /** @var User $user */
            $user = $request->user();
            $query = $user->organizations()->getQuery();
        }

        $organizations = $query
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn ($organization) => OrganizationData::fromModel($organization));

        return OrganizationData::collect($organizations, PaginatedDataCollection::class);
    }

    /**
     * Create a new organization owned by the authenticated user.
     */
    public function store(StoreOrganizationRequest $request, CreateOrganizationAction $createOrganization): OrganizationData
    {
        $this->requirePersonalAccessToken($request);

        /** @var User $user */
        $user = $request->user();

        return $createOrganization->handle(
            name: $request->validated('name'),
            ownerUuid: $user->uuid,
        );
    }

    public function show(Request $request, Organization $organization): OrganizationData
    {
        $this->authorize('view', $organization);

        return OrganizationData::fromModel($organization);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationData
    {
        $user = $request->user();
        $isOwner = $user !== null && $organization->owner_uuid === $user->uuid;

        $attributes = ['name' => $request->validated('name')];

        if ($isOwner && $request->has('slug')) {
            $attributes['slug'] = $request->validated('slug');
        }

        $organization->update($attributes);

        return OrganizationData::fromModel($organization->refresh());
    }

    public function destroy(Request $request, Organization $organization): Response
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->noContent();
    }
}
