<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Auth\Actions\SyncUserGitHubCredentialAction;
use App\Domains\Auth\Actions\UpdateUserGitHubProfileAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ConnectGitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        /** @var GithubProvider $driver */
        $driver = Socialite::driver('github');

        return $driver
            ->scopes(['repo', 'read:org'])
            ->redirect();
    }

    public function callback(
        UpdateUserGitHubProfileAction $updateProfile,
        SyncUserGitHubCredentialAction $syncCredential,
    ): RedirectResponse {
        try {
            /** @var SocialiteUser $githubUser */
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception) {
            return redirect()->route('settings.git-credentials')
                ->with('error', 'GitHub authentication failed. Please try again.');
        }

        /** @var User $user */
        $user = Auth::user();

        $updateProfile->handle($user, $githubUser, $githubUser->token);

        $updated = $syncCredential->handle($user, $githubUser->token);

        $message = $updated
            ? 'GitHub credentials updated successfully.'
            : 'GitHub credentials connected successfully.';

        return redirect()->route('settings.git-credentials')
            ->with('status', $message);
    }
}
