<?php

namespace App\Providers;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use App\Domains\Mirror\Services\Http\DnsHostResolver;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\GitLab\GitLabExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HostResolverInterface::class, DnsHostResolver::class);
    }

    public function boot(): void
    {
        URL::forceHttps(str_starts_with(config('app.url'), 'https://'));

        Event::listen(Login::class, AcceptPendingInvitationListener::class);
        Event::listen(Registered::class, AcceptPendingInvitationListener::class);
        Event::listen(SocialiteWasCalled::class, GitLabExtendSocialite::class.'@handle');

        // Throttling may run before the token is resolved, so key on the credential itself
        RateLimiter::for('artifact-uploads', fn (Request $request) => Limit::perMinute(config('pricore.uploads.rate_limit_per_minute'))
            ->by(hash('sha256', $request->header('Authorization') ?? (string) $request->ip())));

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
}
