<?php

namespace App\Domains\Token\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Domains\Token\Contracts\Data\AccessTokenData;
use App\Domains\Token\Requests\StoreAccessTokenRequest;
use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function __construct(
        protected CreateAccessTokenAction $createAccessToken
    ) {}

    public function index(Organization $organization): Response
    {
        $tokens = AccessToken::query()
            ->where('organization_uuid', $organization->uuid)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AccessToken $token) => AccessTokenData::fromModel($token));

        return Inertia::render('organizations/settings/tokens', [
            'organization' => OrganizationData::fromModel($organization),
            'tokens' => $tokens,
        ]);
    }

    public function store(StoreAccessTokenRequest $request, Organization $organization): Response
    {
        $result = $this->createAccessToken->handle(
            organization: $organization,
            user: null,
            name: $request->validated('name'),
            expiresAt: $request->validated('expires_at') ? now()->parse($request->validated('expires_at')) : null
        );

        $tokens = AccessToken::query()
            ->where('organization_uuid', $organization->uuid)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AccessToken $token) => AccessTokenData::fromModel($token));

        return Inertia::render('organizations/settings/tokens', [
            'organization' => OrganizationData::fromModel($organization),
            'tokens' => $tokens,
            'tokenCreated' => $result,
        ]);
    }

    public function destroy(Organization $organization, AccessToken $token): RedirectResponse
    {
        if ($token->organization_uuid !== $organization->uuid) {
            abort(403);
        }

        $token->delete();

        return to_route('organizations.settings.tokens.index', $organization)
            ->with('status', 'Token revoked successfully.');
    }
}
