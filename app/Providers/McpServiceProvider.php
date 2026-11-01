<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;

        // Passport only serves as the authorization server for MCP clients
        if (! config('pricore.mcp.enabled')) {
            Passport::ignoreRoutes();
        }
    }

    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

        Passport::authorizationView(fn (array $parameters): SymfonyResponse => $this->authorizationView($parameters));

        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(config('pricore.mcp.rate_limit_per_minute'))
            ->by($request->user()?->getAuthIdentifier() ?? (string) $request->ip()));

        // Client registration is unauthenticated, so keep it from being used to flood the clients table
        RateLimiter::for('mcp-oauth', fn (Request $request) => Limit::perMinute(30)->by((string) $request->ip()));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function authorizationView(array $parameters): SymfonyResponse
    {
        $client = $parameters['client'];
        $authToken = $parameters['authToken'];

        if (! $client instanceof Client || ! is_string($authToken)) {
            throw new LogicException('Passport passed unexpected authorization view parameters.');
        }

        $redirectUri = request()->query('redirect_uri');

        return Inertia::render('auth/authorize-mcp', [
            'client' => [
                'id' => (string) $client->getKey(),
                'name' => $client->name,
            ],
            'redirectHost' => is_string($redirectUri) ? $this->redirectHost($redirectUri) : null,
            'authToken' => $authToken,
            'csrfToken' => csrf_token(),
            // Passport's routes are absent when MCP is disabled, so Wayfinder cannot generate helpers for them
            'approveUrl' => route('passport.authorizations.approve'),
            'denyUrl' => route('passport.authorizations.deny'),
        ])->toResponse(request());
    }

    protected function redirectHost(string $redirectUri): ?string
    {
        $scheme = parse_url($redirectUri, PHP_URL_SCHEME);
        $host = parse_url($redirectUri, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        $port = parse_url($redirectUri, PHP_URL_PORT);

        return in_array($scheme, ['http', 'https'], true)
            ? $host.($port ? ':'.$port : '')
            : $scheme.'://'.$host;
    }
}
