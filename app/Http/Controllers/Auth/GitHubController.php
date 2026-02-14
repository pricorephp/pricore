<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationGitCredential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['user:email', 'repo', 'read:org'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
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

            $this->refreshOrganizationCredentials($user);

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

            $this->refreshOrganizationCredentials($user);

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

    private function refreshOrganizationCredentials(User $user): void
    {
        OrganizationGitCredential::where('source_user_uuid', $user->uuid)
            ->each(function (OrganizationGitCredential $credential) use ($user) {
                $credential->update([
                    'credentials' => ['token' => $user->github_token],
                ]);
            });
    }
}
