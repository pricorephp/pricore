<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ComposerTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolveOrganization($request);

        $token = $this->extractToken($request);
        $accessToken = $token ? $this->findAccessToken($token) : null;

        if ($accessToken && $accessToken->isValid() && $this->canAccessOrganization($accessToken, $organization)) {
            $accessToken->markAsUsed();

            $request->merge(['accessToken' => $accessToken]);

            return $next($request);
        }

        // Fall back to anonymous access when the organization allows it
        if ($organization?->anonymous_access_enabled) {
            $request->merge(['accessToken' => null]);

            return $next($request);
        }

        return $this->unauthorized();
    }

    protected function resolveOrganization(Request $request): ?Organization
    {
        $organization = $request->route('organization');

        // Resolve the organization from slug if route model binding hasn't run yet
        if (is_string($organization)) {
            return Organization::where('slug', $organization)->first();
        }

        return $organization instanceof Organization ? $organization : null;
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! $header) {
            return null;
        }

        // Check for Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        // Check for Basic auth (token as password, username is ignored)
        if (preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
            $decoded = base64_decode($matches[1], true);
            if ($decoded === false) {
                return null;
            }

            // Basic auth format: username:password
            // The token must be in the password field (e.g., "token:YOUR_TOKEN")
            $parts = explode(':', $decoded, 2);

            return $parts[1] ?? null;
        }

        return null;
    }

    protected function findAccessToken(string $token): ?AccessToken
    {
        $tokenHash = hash('sha256', $token);

        return AccessToken::query()
            ->where('token_hash', $tokenHash)
            ->with(['organization', 'user'])
            ->first();
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
}
