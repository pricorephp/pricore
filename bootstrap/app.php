<?php

use App\Http\Middleware\ComposerTokenAuth;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackOrganizationAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
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
        ]);

        $middleware->alias([
            'composer.token' => ComposerTokenAuth::class,
            'track.organization' => TrackOrganizationAccess::class,
        ]);
    })
    ->withCommands([
        __DIR__.'/../app/Domains/Repository/Commands',
        __DIR__.'/../app/Domains/Token/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->respond(function (Response $response) {
            if (app()->hasDebugModeEnabled() && $response->getStatusCode() === 500) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [403, 404, 500, 503])) {
                return Inertia::render('error', [
                    'status' => $response->getStatusCode(),
                ])
                    ->toResponse(request())
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
