<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');
        $user = $request->user();

        if (! $organization instanceof Organization || $user === null) {
            abort(403);
        }

        $isMember = $organization->members()
            ->where('user_uuid', $user->uuid)
            ->exists();

        if (! $isMember) {
            abort(403);
        }

        return $next($request);
    }
}
