<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Auth\Contracts\Enums\GitLabOAuthIntent;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ConnectGitLabController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        session(['gitlab_oauth_intent' => GitLabOAuthIntent::Connect]);

        /** @var \SocialiteProviders\GitLab\Provider $driver */
        $driver = Socialite::driver('gitlab');

        return $driver
            ->scopes(['api'])
            ->redirect();
    }
}
