<?php

namespace App\Http\Middleware;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Domains\Token\Services\TokenScopeChecker;
use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTokenScope
{
    public function __construct(
        protected TokenScopeChecker $scopeChecker,
    ) {}

    /**
     * Usage: ->middleware('scope:write:repositories'). Multiple comma-separated
     * scopes are treated as "any of"; chain two scope middlewares for "all of".
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $accessToken = $request->get('accessToken');

        if (! $accessToken instanceof AccessToken) {
            return $this->unauthorized();
        }

        $required = array_map(
            fn (string $scope) => TokenScope::from($scope),
            $scopes,
        );

        if (! $this->scopeChecker->hasAny($accessToken, ...$required)) {
            return response()->json([
                'message' => 'Insufficient token scope.',
                'required_scope' => $scopes[0] ?? null,
            ], 403);
        }

        return $next($request);
    }

    protected function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401, [
            'WWW-Authenticate' => 'Bearer realm="Pricore"',
        ]);
    }
}
