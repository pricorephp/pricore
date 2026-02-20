<?php

namespace App\Domains\Repository\Http\Middleware;

use App\Models\Repository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGitHubWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Repository|null $repository */
        $repository = $request->route('repository');

        if (! $repository || ! $repository->webhook_secret) {
            abort(403, 'Invalid webhook configuration.');
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            abort(403, 'Missing signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $repository->webhook_secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
