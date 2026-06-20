<?php

namespace App\Domains\Token\Services;

use App\Models\AccessToken;
use Illuminate\Http\Request;

class AccessTokenResolver
{
    /**
     * Resolve the access token from the request's Authorization header, if any.
     */
    public function fromRequest(Request $request): ?AccessToken
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return null;
        }

        return $this->findAccessToken($token);
    }

    /**
     * Extract the plaintext token from a Bearer or Basic Authorization header.
     */
    public function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! $header) {
            return null;
        }

        // Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        // Basic auth (token as password, username is ignored)
        if (preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
            $decoded = base64_decode($matches[1], true);
            if ($decoded === false) {
                return null;
            }

            // Basic auth format: username:password — the token is in the password field.
            $parts = explode(':', $decoded, 2);

            return $parts[1] ?? null;
        }

        return null;
    }

    public function findAccessToken(string $token): ?AccessToken
    {
        $tokenHash = hash('sha256', $token);

        return AccessToken::query()
            ->where('token_hash', $tokenHash)
            ->with(['organization', 'user'])
            ->first();
    }
}
