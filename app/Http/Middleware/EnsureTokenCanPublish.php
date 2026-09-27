<?php

namespace App\Http\Middleware;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after composer.token. A personal token publishes on behalf of its user,
 * so that user must still be allowed to manage packages: a member who was
 * demoted keeps a token that can read, but no longer publish.
 */
class EnsureTokenCanPublish
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->attributes->get('accessToken');
        $organization = $request->route('organization');

        if (is_string($organization)) {
            $organization = Organization::query()->where('slug', $organization)->first();
        }

        if (! $accessToken instanceof AccessToken || ! $organization instanceof Organization) {
            return $this->forbidden('This token cannot publish packages.');
        }

        if (! $accessToken->hasScope(TokenScope::Write)) {
            return $this->forbidden('This token cannot publish packages. Create a token with publishing enabled.');
        }

        if ($accessToken->user_uuid !== null && ! $accessToken->user?->can('managePackages', $organization)) {
            return $this->forbidden('Only organization admins can publish packages.');
        }

        return $next($request);
    }

    protected function forbidden(string $message): Response
    {
        return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
    }
}
