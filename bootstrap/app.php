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
use Sentry\Laravel\Integration;

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
    })->create();
