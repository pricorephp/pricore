<?php

namespace App\Providers;

use App\Listeners\AcceptPendingInvitationListener;
use App\Listeners\VerifyHealthDependencies;
use App\Models\AccessToken;
use App\Models\Mirror;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationSshKey;
use App\Models\Package;
use App\Models\Repository;
use App\Models\User;
use App\Services\Http\OutboundUrlGuard;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\GitLab\GitLabExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared so its per-request resolution memo is actually reused.
        $this->app->singleton(OutboundUrlGuard::class);
    }

    public function boot(): void
    {
        URL::forceHttps(str_starts_with(config('app.url'), 'https://'));

        $this->configureTrustedProxies();
        $this->configureRateLimiting();

        Event::listen(Login::class, AcceptPendingInvitationListener::class);
        Event::listen(Registered::class, AcceptPendingInvitationListener::class);
        Event::listen(SocialiteWasCalled::class, GitLabExtendSocialite::class.'@handle');
        Event::listen(DiagnosingHealth::class, VerifyHealthDependencies::class);

        Relation::enforceMorphMap([
            'repository' => Repository::class,
            'package' => Package::class,
            'access_token' => AccessToken::class,
            'user' => User::class,
            'organization_invitation' => OrganizationInvitation::class,
            'organization_ssh_key' => OrganizationSshKey::class,
            'mirror' => Mirror::class,
        ]);
    }

    /**
     * Set here rather than in bootstrap/app.php: that callback runs before the config
     * repository is bound, so it could only read env() — which returns nothing once
     * `config:cache` has run, because .env is then never loaded. Losing proxy trust is
     * silent and damaging: behind a TLS-terminating proxy every generated URL falls
     * back to http, including the metadata-url handed to Composer clients.
     */
    private function configureTrustedProxies(): void
    {
        $configured = (string) config('pricore.trusted_proxies', '');

        if (trim($configured) === '') {
            return;
        }

        $proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));

        TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
    }

    /**
     * Laravel leaves throttling off the `api` group by default, which would otherwise
     * leave the Composer endpoints and webhook receivers completely unmetered.
     */
    private function configureRateLimiting(): void
    {
        // Keyed by token so one noisy consumer cannot starve another. Needs to stay
        // generous — a single `composer update` issues hundreds of parallel requests.
        RateLimiter::for('composer', function (Request $request) {
            $token = $request->header('Authorization') ?: $request->ip();

            return Limit::perMinute((int) config('pricore.rate_limits.composer'))
                ->by('composer:'.sha1((string) $token));
        });

        // Failed authentication is throttled inside ComposerTokenAuth rather than by a
        // named limiter here, so that only failures count against the limit.

        RateLimiter::for('webhooks', function (Request $request) {
            $repository = $request->route('repository');

            $key = $repository instanceof Repository
                ? $repository->uuid
                : (string) $request->ip();

            return Limit::perMinute((int) config('pricore.rate_limits.webhooks'))
                ->by('webhooks:'.$key);
        });
    }
}
