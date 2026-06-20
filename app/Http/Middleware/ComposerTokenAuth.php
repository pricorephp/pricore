<?php

namespace App\Http\Middleware;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Domains\Token\Services\AccessTokenResolver;
use App\Domains\Token\Services\TokenScopeChecker;
use App\Models\AccessToken;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ComposerTokenAuth
{
    public function __construct(
        protected AccessTokenResolver $accessTokenResolver,
        protected TokenScopeChecker $scopeChecker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $this->accessTokenResolver->fromRequest($request);

        if (! $accessToken || ! $accessToken->isValid()) {
            return $this->unauthorized();
        }

        if (! $this->scopeChecker->hasScope($accessToken, TokenScope::Composer)) {
            return $this->forbidden();
        }

        $organization = $request->route('organization');

        // Resolve the organization from slug if route model binding hasn't run yet
        if (is_string($organization)) {
            $organization = Organization::where('slug', $organization)->first();
        }

        if (! $this->canAccessOrganization($accessToken, $organization)) {
            return $this->unauthorized();
        }

        $accessToken->markAsUsed();

        $request->merge(['accessToken' => $accessToken]);

        return $next($request);
    }

    protected function canAccessOrganization(AccessToken $accessToken, ?Organization $organization): bool
    {
        if (! $organization) {
            return false;
        }

        // Organization-scoped token
        if ($accessToken->organization_uuid) {
            return $accessToken->organization_uuid === $organization->uuid;
        }

        // User-scoped token - check if user is member of organization
        if ($accessToken->user_uuid) {
            return $organization->members()
                ->where('user_uuid', $accessToken->user_uuid)
                ->exists();
        }

        return false;
    }

    protected function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthorized',
        ], 401, [
            'WWW-Authenticate' => 'Bearer realm="Pricore"',
        ]);
    }

    protected function forbidden(): Response
    {
        return response()->json([
            'message' => 'This token does not have Composer registry access.',
        ], 403);
    }
}
