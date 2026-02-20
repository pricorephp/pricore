<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->route('organization') instanceof Organization) {
            $organization = $request->route('organization');

            $request->user()->organizations()->updateExistingPivot(
                $organization->uuid,
                ['last_accessed_at' => now()]
            );
        }

        return $response;
    }
}
