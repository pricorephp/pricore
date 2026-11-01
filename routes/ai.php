<?php

use App\Domains\Mcp\Servers\PricoreServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckToken;

if (config('pricore.mcp.enabled')) {
    Route::middleware('throttle:mcp-oauth')->group(fn () => Mcp::oauthRoutes());

    Mcp::web('/mcp', PricoreServer::class)
        ->middleware(['auth:api', CheckToken::using(Registrar::OAUTH_SCOPE), 'throttle:mcp'])
        ->name('mcp');
}
