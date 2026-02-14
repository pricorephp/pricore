<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        /** @var \Laravel\Socialite\Two\GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['user:email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
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

    public function connect(Organization $organization): SymfonyRedirectResponse
    {
        session(['github_connect_organization' => $organization->slug]);

        /** @var \Laravel\Socialite\Two\GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['repo', 'read:org'])
            ->redirect();
    }

    public function connectCallback(): RedirectResponse
    {
        $organizationSlug = session()->pull('github_connect_organization');

        if (! $organizationSlug) {
            return redirect()->route('dashboard')->with('error', 'No organization found for GitHub connection.');
        }

        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();

        try {
            /** @var SocialiteUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            return redirect()
                ->route('organizations.settings.git-credentials.index', $organization)
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

        if ($organization->gitCredentials()->where('provider', 'github')->exists()) {
            return redirect()
                ->route('organizations.settings.git-credentials.index', $organization)
                ->with('error', 'GitHub credentials already exist for this organization. Please update the existing credentials instead.');
        }

        OrganizationGitCredential::create([
            'organization_uuid' => $organization->uuid,
            'provider' => 'github',
            'credentials' => ['token' => $githubUser->token],
            'source_user_uuid' => $user->uuid,
        ]);

        $this->refreshOrganizationCredentials($user);

        return redirect()
            ->route('organizations.settings.git-credentials.index', $organization)
            ->with('status', 'GitHub credentials connected successfully.');
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
