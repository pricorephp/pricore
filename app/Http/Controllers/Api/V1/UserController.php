<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class UserController extends ApiController
{
    /**
     * Get the authenticated user's profile.
     *
     * @return array{uuid: string, name: string, email: string, avatar: string|null}
     */
    public function show(Request $request): array
    {
        $this->requirePersonalAccessToken($request);

        /** @var User $user */
        $user = $request->user();

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
        ];
    }

    /**
     * List the organizations the authenticated user belongs to.
     *
     * @return PaginatedDataCollection<array-key, OrganizationData>
     */
    public function organizations(Request $request): PaginatedDataCollection
    {
        $this->requirePersonalAccessToken($request);

        /** @var User $user */
        $user = $request->user();

        $organizations = $user->organizations()
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn ($organization) => OrganizationData::fromModel($organization));

        return OrganizationData::collect($organizations, PaginatedDataCollection::class);
    }
}
