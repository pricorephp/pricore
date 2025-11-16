<?php

namespace App\Domains\Token\Http\Controllers;

use App\Domains\Token\Actions\CreateAccessTokenAction;
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
            ->map(fn (AccessToken $token) => [
                'uuid' => $token->uuid,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ]);

        return Inertia::render('organizations/settings/tokens', [
            'organization' => $organization,
            'tokens' => $tokens,
        ]);
    }

    public function store(StoreAccessTokenRequest $request, Organization $organization): RedirectResponse
    {
        $result = $this->createAccessToken->handle(
            organization: $organization,
            user: null,
            name: $request->validated('name'),
            expiresAt: $request->validated('expires_at') ? now()->parse($request->validated('expires_at')) : null,
            scopes: $request->validated('scopes')
        );

        return to_route('organizations.settings.tokens.index', $organization)
            ->with('tokenCreated', [
                'plainToken' => $result->plainToken,
                'name' => $result->accessToken->name,
                'expires_at' => $result->accessToken->expires_at,
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
