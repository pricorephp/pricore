<?php

namespace App\Providers;

use App\Listeners\AcceptPendingInvitationListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, AcceptPendingInvitationListener::class);
        Event::listen(Registered::class, AcceptPendingInvitationListener::class);
    }
}
