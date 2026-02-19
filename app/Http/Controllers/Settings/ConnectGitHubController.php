<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Auth\Contracts\Enums\GitHubOAuthIntent;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ConnectGitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        session(['github_oauth_intent' => GitHubOAuthIntent::Connect]);

        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['repo', 'read:org'])
            ->redirect();
    }
}
