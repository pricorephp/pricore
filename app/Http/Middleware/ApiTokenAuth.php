<?php

namespace App\Http\Middleware;

use App\Domains\Token\Services\AccessTokenResolver;
use App\Models\AccessToken;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function __construct(
        protected AccessTokenResolver $accessTokenResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $this->accessTokenResolver->fromRequest($request);

        if (! $accessToken || ! $accessToken->isValid()) {
            return $this->unauthorized();
        }

        // Establish the acting user so existing policies and Form Requests work:
        // a personal access token acts as its user; an organization token acts as
        // the organization's owner (always a member with the owner role).
        if ($accessToken->user_uuid && $accessToken->user) {
            Auth::setUser($accessToken->user);
        } elseif ($accessToken->organization_uuid && $accessToken->organization?->owner) {
            Auth::setUser($accessToken->organization->owner);
            $request->attributes->set('api_token_organization', $accessToken->organization);
        } else {
            // Token references a missing user/organization (e.g. soft-deleted org).
            return $this->unauthorized();
        }

        // An organization-scoped token may only ever act on its own organization,
        // regardless of who its owner is a member of elsewhere.
        if ($accessToken->organization_uuid && ! $this->organizationMatches($request, $accessToken)) {
            return $this->forbidden();
        }

        $accessToken->markAsUsed();

        $request->merge(['accessToken' => $accessToken]);

        return $next($request);
    }

    protected function organizationMatches(Request $request, AccessToken $accessToken): bool
    {
        $routeOrganization = $request->route('organization');

        // No organization in the route (e.g. /user, /organizations) — nothing to mismatch.
        if ($routeOrganization === null) {
            return true;
        }

        if ($routeOrganization instanceof Organization) {
            return $routeOrganization->uuid === $accessToken->organization_uuid;
        }

        $organization = Organization::where('slug', $routeOrganization)->first();

        return $organization !== null && $organization->uuid === $accessToken->organization_uuid;
    }

    protected function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401, [
            'WWW-Authenticate' => 'Bearer realm="Pricore"',
        ]);
    }

    protected function forbidden(): Response
    {
        return response()->json([
            'message' => 'This token cannot access this organization.',
        ], 403);
    }
}
