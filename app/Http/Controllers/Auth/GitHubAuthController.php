<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Auth\Actions\FindOrCreateGitHubUserAction;
use App\Domains\Auth\Actions\SyncUserGitHubCredentialAction;
use App\Domains\Auth\Actions\UpdateUserGitHubProfileAction;
use App\Domains\Auth\Contracts\Enums\GitHubOAuthIntent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as TwoUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        session(['github_oauth_intent' => GitHubOAuthIntent::Login]);

        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['user:email'])
            ->redirect();
    }

    public function callback(
        FindOrCreateGitHubUserAction $findOrCreateUser,
        UpdateUserGitHubProfileAction $updateProfile,
        SyncUserGitHubCredentialAction $syncCredential,
    ): RedirectResponse {
        $intent = session()->pull('github_oauth_intent', GitHubOAuthIntent::Login);

        try {
            /** @var TwoUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            if ($intent->isConnect()) {
                return redirect()->route('settings.git-credentials')
                    ->with('error', 'GitHub authentication failed. Please try again.');
            }

            return redirect()->route('login')->with('error', 'GitHub authentication failed. Please try again.');
        }

        if ($intent->isConnect()) {
            return $this->handleConnect($githubUser, $updateProfile, $syncCredential);
        }

        return $this->handleLogin($githubUser, $findOrCreateUser);
    }

    private function handleLogin(SocialiteUser $githubUser, FindOrCreateGitHubUserAction $findOrCreateUser): RedirectResponse
    {
        if (empty($githubUser->getEmail())) {
            return redirect()->route('login')->with('error', 'We could not retrieve your email address from GitHub. Please ensure your email is public or try another sign-in method.');
        }

        /** @var TwoUser $githubUser */
        $user = $findOrCreateUser->handle($githubUser, $githubUser->token);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function handleConnect(
        SocialiteUser $githubUser,
        UpdateUserGitHubProfileAction $updateProfile,
        SyncUserGitHubCredentialAction $syncCredential,
    ): RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'You must be logged in to connect your GitHub account.');
        }

        /** @var User $user */
        $user = Auth::user();

        $updateProfile->handle($user, $githubUser);

        /** @var TwoUser $githubUser */
        $updated = $syncCredential->handle($user, $githubUser->token);

        $message = $updated
            ? 'GitHub credentials updated successfully.'
            : 'GitHub credentials connected successfully.';

        return redirect()->route('settings.git-credentials')
            ->with('status', $message);
    }
}
