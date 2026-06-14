<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Domains\Token\Actions\UpdateAccessTokenAction;
use App\Domains\Token\Contracts\Data\AccessTokenData;
use App\Domains\Token\Contracts\Data\TokenCreatedData;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Domains\Token\Requests\StoreAccessTokenRequest;
use App\Domains\Token\Requests\UpdateAccessTokenRequest;
use App\Domains\Token\Services\TokenScopeChecker;
use App\Models\AccessToken;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class TokenController extends ApiController
{
    public function __construct(
        protected CreateAccessTokenAction $createAccessToken,
        protected UpdateAccessTokenAction $updateAccessToken,
        protected TokenScopeChecker $scopeChecker,
        protected RecordActivityTask $recordActivity,
    ) {}

    /**
     * @return PaginatedDataCollection<array-key, AccessTokenData>
     */
    public function index(Request $request, Organization $organization): PaginatedDataCollection
    {
        $this->authorize('viewSettings', $organization);

        $tokens = $organization->accessTokens()
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn ($token) => AccessTokenData::fromModel($token));

        return AccessTokenData::collect($tokens, PaginatedDataCollection::class);
    }

    public function store(StoreAccessTokenRequest $request, Organization $organization): TokenCreatedData
    {
        $this->authorize('viewSettings', $organization);

        $scopes = $request->validated('scopes') ?? [TokenScope::Composer->value];
        $this->assertCanGrantScopes($request, $scopes);

        return $this->createAccessToken->handle(
            organization: $organization,
            user: null,
            name: $request->validated('name'),
            expiresAt: $request->validated('expires_at') ? now()->parse($request->validated('expires_at')) : null,
            scopes: $scopes,
        );
    }

    public function update(UpdateAccessTokenRequest $request, Organization $organization, AccessToken $token): AccessTokenData
    {
        $this->authorize('viewSettings', $organization);
        abort_unless($token->organization_uuid === $organization->uuid, 404);

        $scopes = $request->validated('scopes');
        if ($scopes !== null) {
            $this->assertCanGrantScopes($request, $scopes);
        }

        $this->updateAccessToken->handle(
            accessToken: $token,
            name: $request->validated('name'),
            scopes: $scopes,
            actor: $request->user(),
        );

        return AccessTokenData::fromModel($token->refresh());
    }

    public function destroy(Request $request, Organization $organization, AccessToken $token): Response
    {
        $this->authorize('viewSettings', $organization);
        abort_unless($token->organization_uuid === $organization->uuid, 404);

        $this->recordActivity->handle(
            organization: $organization,
            type: ActivityType::TokenRevoked,
            subject: $token,
            actor: $request->user(),
            properties: ['name' => $token->name],
        );

        $token->delete();

        return response()->noContent();
    }

    /**
     * A token may only grant scopes it itself holds (legacy null-scope tokens may grant any).
     *
     * @param  array<int, string>  $scopeValues
     */
    private function assertCanGrantScopes(Request $request, array $scopeValues): void
    {
        $accessToken = $this->accessToken($request);

        foreach ($scopeValues as $value) {
            if (! $this->scopeChecker->hasScope($accessToken, TokenScope::from($value))) {
                abort(403, "Token cannot grant the '{$value}' scope it does not itself hold.");
            }
        }
    }
}
