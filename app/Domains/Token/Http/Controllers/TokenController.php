<?php

namespace App\Domains\Token\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Domains\Token\Actions\UpdateAccessTokenAction;
use App\Domains\Token\Contracts\Data\AccessTokenData;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Domains\Token\Requests\StoreAccessTokenRequest;
use App\Domains\Token\Requests\UpdateAccessTokenRequest;
use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CreateAccessTokenAction $createAccessToken,
        protected UpdateAccessTokenAction $updateAccessToken,
        protected RecordActivityTask $recordActivity,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

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
        $this->authorize('viewSettings', $organization);

        $result = $this->createAccessToken->handle(
            organization: $organization,
            user: null,
            name: $request->validated('name'),
            expiresAt: $request->validated('expires_at') ? now()->parse($request->validated('expires_at')) : null,
            scopes: $request->validated('scopes') ?? [TokenScope::Composer->value],
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

    public function update(UpdateAccessTokenRequest $request, Organization $organization, AccessToken $token): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        if ($token->organization_uuid !== $organization->uuid) {
            abort(403);
        }

        $this->updateAccessToken->handle(
            accessToken: $token,
            name: $request->validated('name'),
            scopes: $request->validated('scopes'),
            actor: $request->user(),
        );

        return to_route('organizations.settings.tokens.index', $organization)
            ->with('status', 'Token updated successfully.');
    }

    public function destroy(Organization $organization, AccessToken $token): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        if ($token->organization_uuid !== $organization->uuid) {
            abort(403);
        }

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::TokenRevoked,
            subject: $token,
            actor: auth()->user(),
            properties: ['name' => $token->name],
        );

        $token->delete();

        return to_route('organizations.settings.tokens.index', $organization)
            ->with('status', 'Token revoked successfully.');
    }
}
