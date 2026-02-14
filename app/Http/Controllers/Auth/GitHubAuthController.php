<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Auth\Actions\FindOrCreateGitHubUserAction;
use App\Domains\Auth\Actions\SyncUserGitHubCredentialAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['user:email'])
            ->redirect();
    }

    public function callback(
        FindOrCreateGitHubUserAction $findOrCreateUser,
        SyncUserGitHubCredentialAction $syncCredential,
    ): RedirectResponse {
        try {
            /** @var SocialiteUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            return redirect()->route('login')->with('error', 'GitHub authentication failed. Please try again.');
        }

        if (empty($githubUser->getEmail())) {
            return redirect()->route('login')->with('error', 'We could not retrieve your email address from GitHub. Please ensure your email is public or try another sign-in method.');
        }

        $user = $findOrCreateUser->handle($githubUser, $githubUser->token);

        $syncCredential->handle($user, $githubUser->token);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
