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
        $token = $this->extractToken($request);

        if (! $token) {
            return $this->unauthorized();
        }

        $accessToken = $this->findAccessToken($token);

        if (! $accessToken || ! $accessToken->isValid()) {
            return $this->unauthorized();
        }

        $organization = $request->route('organization');

        if (! $this->canAccessOrganization($accessToken, $organization)) {
            return $this->unauthorized();
        }

        $accessToken->markAsUsed();

        $request->merge(['accessToken' => $accessToken]);

        return $next($request);
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
