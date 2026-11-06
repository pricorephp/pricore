<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\ComposerTokenAuth;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsureTokenCanPublish;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTokenScope;
use App\Http\Middleware\TrackOrganizationAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Mcp\Server\Middleware\AddAuthorizationFramingHeaders;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Webauthn\Exception\WebauthnException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->group(base_path('routes/composer.php'));

            Route::middleware('api')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $trustedProxies = explode(',', env('TRUSTED_PROXIES', ''));

        if ($trustedProxies === ['*']) {
            $trustedProxies = '*';
        }

        $middleware->trustProxies(at: $trustedProxies);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AddAuthorizationFramingHeaders::class,
        ]);

        // A group rather than an alias so installed packages can append their
        // own checks to every publishing endpoint.
        $middleware->group('composer.publish', [
            EnsureTokenCanPublish::class,
        ]);

        $middleware->alias([
            'composer.token' => ComposerTokenAuth::class,
            'api.token' => ApiTokenAuth::class,
            'scope' => RequireTokenScope::class,
            'json' => ForceJsonResponse::class,
            'organization.member' => EnsureOrganizationMembership::class,
            'track.organization' => TrackOrganizationAccess::class,
        ]);
    })
    ->withCommands([
        __DIR__.'/../app/Domains/Repository/Commands',
        __DIR__.'/../app/Domains/Token/Commands',
        __DIR__.'/../app/Domains/Mirror/Commands',
        __DIR__.'/../app/Domains/Security/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // A passkey ceremony that fails verification (bad signature, wrong origin) is a user error, not a server error
        $exceptions->map(WebauthnException::class, fn () => InvalidPasskeyException::make('This passkey could not be verified. Please try again.'));

        // MCP clients need a 401 with WWW-Authenticate rather than a login redirect
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('mcp') || $request->expectsJson());

        $exceptions->respond(function (Response $response) {
            if (app()->hasDebugModeEnabled() && $response->getStatusCode() === 500) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [403, 404, 500, 503]) && ! request()->expectsJson()) {
                return Inertia::render('error', [
                    'status' => $response->getStatusCode(),
                ])
                    ->toResponse(request())
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
