<?php

namespace App\Providers;

use App\Listeners\AcceptPendingInvitationListener;
use App\Models\AccessToken;
use App\Models\Mirror;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationSshKey;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\GitLab\GitLabExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        URL::forceHttps(str_starts_with(config('app.url'), 'https://'));

        Event::listen(Login::class, AcceptPendingInvitationListener::class);
        Event::listen(Registered::class, AcceptPendingInvitationListener::class);
        Event::listen(SocialiteWasCalled::class, GitLabExtendSocialite::class.'@handle');

        Relation::enforceMorphMap([
            'repository' => Repository::class,
            'package' => Package::class,
            'access_token' => AccessToken::class,
            'user' => User::class,
            'organization_invitation' => OrganizationInvitation::class,
            'organization_ssh_key' => OrganizationSshKey::class,
            'mirror' => Mirror::class,
        ]);

        RateLimiter::for('api', function (Request $request) {
            $accessToken = $request->get('accessToken');
            $key = $accessToken instanceof AccessToken ? $accessToken->uuid : (string) $request->ip();

            return Limit::perMinute(120)->by($key);
        });

        // Scramble auto-allows API docs in the local environment; elsewhere
        // restrict the interactive docs UI to authenticated users.
        Gate::define('viewApiDocs', fn (?User $user) => $user !== null);
    }
}
