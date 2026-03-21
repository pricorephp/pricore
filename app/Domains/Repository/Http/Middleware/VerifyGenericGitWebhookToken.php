<?php

namespace App\Domains\Repository\Http\Middleware;

use App\Models\Repository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGenericGitWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Repository|null $repository */
        $repository = $request->route('repository');

        if (! $repository || ! $repository->webhook_secret) {
            abort(403, 'Invalid webhook configuration.');
        }

        // Accept token via Authorization header, query parameter, or X-Webhook-Token header
        $token = $request->bearerToken()
            ?? $request->header('X-Webhook-Token')
            ?? $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(403, 'Missing token.');
        }

        if (! hash_equals($repository->webhook_secret, $token)) {
            abort(403, 'Invalid token.');
        }

        return $next($request);
    }
}
