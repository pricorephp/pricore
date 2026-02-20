<?php

namespace App\Domains\Token\Http\Controllers;

use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Domains\Token\Contracts\Data\AccessTokenData;
use App\Domains\Token\Requests\StoreAccessTokenRequest;
use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserTokenController extends Controller
{
    public function __construct(
        protected CreateAccessTokenAction $createAccessToken
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = AccessToken::query()
            ->where('user_uuid', $user->uuid)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AccessToken $token) => AccessTokenData::fromModel($token));

        return Inertia::render('settings/tokens', [
            'tokens' => $tokens,
        ]);
    }

    public function store(StoreAccessTokenRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->createAccessToken->handle(
            organization: null,
            user: $user,
            name: $request->validated('name'),
            expiresAt: $request->validated('expires_at') ? now()->parse($request->validated('expires_at')) : null
        );

        $tokens = AccessToken::query()
            ->where('user_uuid', $user->uuid)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AccessToken $token) => AccessTokenData::fromModel($token));

        return Inertia::render('settings/tokens', [
            'tokens' => $tokens,
            'tokenCreated' => $result,
        ]);
    }

    public function destroy(Request $request, AccessToken $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($token->user_uuid !== $user->uuid) {
            abort(403);
        }

        $token->delete();

        return to_route('settings.tokens.index')
            ->with('status', 'Token revoked successfully.');
    }
}
