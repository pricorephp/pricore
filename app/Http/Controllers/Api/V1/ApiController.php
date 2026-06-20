<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * The access token authenticating the current request.
     */
    protected function accessToken(Request $request): AccessToken
    {
        $accessToken = $request->get('accessToken');

        abort_unless($accessToken instanceof AccessToken, 401);

        return $accessToken;
    }

    /**
     * Ensure the request is authenticated with a personal (user) access token,
     * not an organization-scoped token.
     */
    protected function requirePersonalAccessToken(Request $request): void
    {
        abort_if(
            $this->accessToken($request)->organization_uuid !== null,
            403,
            'This endpoint requires a personal access token.',
        );
    }

    /**
     * Resolve the requested page size, clamped to a sensible range.
     */
    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }
}
