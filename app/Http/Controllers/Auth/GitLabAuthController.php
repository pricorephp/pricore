<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Auth\Actions\FindOrCreateGitLabUserAction;
use App\Domains\Auth\Actions\SyncUserGitLabCredentialAction;
use App\Domains\Auth\Actions\UpdateUserGitLabProfileAction;
use App\Domains\Auth\Contracts\Enums\GitLabOAuthIntent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as TwoUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitLabAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        session(['gitlab_oauth_intent' => GitLabOAuthIntent::Login]);

        /** @var \SocialiteProviders\GitLab\Provider $driver */
        $driver = Socialite::driver('gitlab');

        return $driver
            ->scopes(['read_user'])
            ->redirect();
    }

    public function callback(
        FindOrCreateGitLabUserAction $findOrCreateUser,
        UpdateUserGitLabProfileAction $updateProfile,
        SyncUserGitLabCredentialAction $syncCredential,
    ): RedirectResponse {
        $intent = session()->pull('gitlab_oauth_intent', GitLabOAuthIntent::Login);

        try {
            /** @var TwoUser $gitlabUser */
            $gitlabUser = Socialite::driver('gitlab')->user();
        } catch (\Exception) {
            if ($intent->isConnect()) {
                return redirect()->route('settings.git-credentials')
                    ->with('error', 'GitLab authentication failed. Please try again.');
            }

            return redirect()->route('login')->with('error', 'GitLab authentication failed. Please try again.');
        }

        if ($intent->isConnect()) {
            return $this->handleConnect($gitlabUser, $updateProfile, $syncCredential);
        }

        return $this->handleLogin($gitlabUser, $findOrCreateUser);
    }

    private function handleLogin(SocialiteUser $gitlabUser, FindOrCreateGitLabUserAction $findOrCreateUser): RedirectResponse
    {
        if (empty($gitlabUser->getEmail())) {
            return redirect()->route('login')->with('error', 'We could not retrieve your email address from GitLab. Please ensure your email is public or try another sign-in method.');
        }

        $existingUser = User::where('gitlab_id', $gitlabUser->getId())
            ->orWhere('email', $gitlabUser->getEmail())
            ->first();

        if (! $existingUser && ! config('fortify.sign_up_enabled') && ! session('invitation_token')) {
            return redirect()->route('login')
                ->with('error', 'Registration is currently closed. You need an invitation to create an account.');
        }

        /** @var TwoUser $gitlabUser */
        $user = $findOrCreateUser->handle($gitlabUser, $gitlabUser->token);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function handleConnect(
        SocialiteUser $gitlabUser,
        UpdateUserGitLabProfileAction $updateProfile,
        SyncUserGitLabCredentialAction $syncCredential,
    ): RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'You must be logged in to connect your GitLab account.');
        }

        /** @var User $user */
        $user = Auth::user();

        $updateProfile->handle($user, $gitlabUser);

        /** @var TwoUser $gitlabUser */
        $updated = $syncCredential->handle($user, $gitlabUser->token);

        $message = $updated
            ? 'GitLab credentials updated successfully.'
            : 'GitLab credentials connected successfully.';

        return redirect()->route('settings.git-credentials')
            ->with('status', $message);
    }
}
