<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['user:email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (session()->has('github_connect_return_url') && Auth::check()) {
            return $this->connectCallback();
        }

        try {
            /** @var SocialiteUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            return redirect()->route('login')->with('error', 'GitHub authentication failed. Please try again.');
        }

        if (empty($githubUser->getEmail())) {
            return redirect()->route('login')->with('error', 'We could not retrieve your email address from GitHub. Please ensure your email is public or try another sign-in method.');
        }

        $user = User::where('github_id', $githubUser->getId())->first();

        if ($user) {
            $user->update([
                'github_token' => $githubUser->token,
                'github_nickname' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            $this->refreshUserGitCredentials($user);

            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        $user = User::where('email', $githubUser->getEmail())->first();

        if ($user) {
            $user->update([
                'github_id' => $githubUser->getId(),
                'github_token' => $githubUser->token,
                'github_nickname' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            $this->refreshUserGitCredentials($user);

            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        $user = User::create([
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'email' => $githubUser->getEmail(),
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
            'github_nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => null,
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    public function connect(): SymfonyRedirectResponse
    {
        session(['github_connect_return_url' => route('settings.git-credentials')]);

        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['repo', 'read:org'])
            ->redirect();
    }

    public function connectCallback(): RedirectResponse
    {
        $returnUrl = session()->pull('github_connect_return_url', route('settings.git-credentials'));

        try {
            /** @var SocialiteUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            return redirect()->to($returnUrl)
                ->with('error', 'GitHub authentication failed. Please try again.');
        }

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'github_token' => $githubUser->token,
            'github_id' => $user->github_id ?? $githubUser->getId(),
            'github_nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
        ]);

        if ($user->gitCredentials()->where('provider', 'github')->exists()) {
            // Update existing credential with new token
            $user->gitCredentials()->where('provider', 'github')->update([
                'credentials' => encrypt(json_encode(['token' => $githubUser->token])),
            ]);

            return redirect()->to($returnUrl)
                ->with('status', 'GitHub credentials updated successfully.');
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => 'github',
            'credentials' => ['token' => $githubUser->token],
        ]);

        return redirect()->to($returnUrl)
            ->with('status', 'GitHub credentials connected successfully.');
    }

    private function refreshUserGitCredentials(User $user): void
    {
        $credential = $user->gitCredentials()->where('provider', 'github')->first();

        if ($credential) {
            $credential->update([
                'credentials' => ['token' => $user->github_token],
            ]);
        }
    }
}
