<?php

namespace App\Domains\Mcp\Http\Controllers;

use App\Domains\Mcp\Contracts\Data\ConnectedAppData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class ConnectedAppController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $connectedApps = $this->activeTokens($user)
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->map(function (Collection $tokens): ConnectedAppData {
                $oldest = $tokens->sortBy('created_at')->firstOrFail();
                $latest = $tokens->sortByDesc('created_at')->firstOrFail();

                return new ConnectedAppData(
                    clientId: $latest->client_id,
                    name: $latest->client->name ?? 'Unknown application',
                    authorizedAt: $oldest->created_at,
                    lastActiveAt: $latest->created_at,
                );
            })
            ->sortByDesc(fn (ConnectedAppData $connectedApp) => $connectedApp->lastActiveAt)
            ->values();

        return Inertia::render('settings/connected-apps', [
            'connectedApps' => $connectedApps,
            'mcpUrl' => route('mcp'),
        ]);
    }

    public function destroy(Request $request, string $client): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokenIds = Token::query()
            ->where('user_id', $user->uuid)
            ->where('client_id', $client)
            ->pluck('id');

        if ($tokenIds->isEmpty()) {
            abort(404);
        }

        Token::query()->whereIn('id', $tokenIds)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);

        return to_route('settings.connected-apps.index')
            ->with('status', 'Application access revoked.');
    }

    /**
     * Tokens that still grant access, either directly or through a refresh token.
     *
     * @return Builder<Token>
     */
    protected function activeTokens(User $user): Builder
    {
        return Token::query()
            ->where('user_id', $user->uuid)
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $query) => $query
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())));
    }
}
