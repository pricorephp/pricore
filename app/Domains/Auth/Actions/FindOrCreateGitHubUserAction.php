<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Exceptions\UnverifiedOAuthEmailException;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateGitHubUserAction
{
    /**
     * Find an existing user by GitHub ID or email, or create a new one.
     * Linking by email requires a verified email on the existing account.
     *
     * @throws UnverifiedOAuthEmailException
     */
    public function handle(SocialiteUser $githubUser, string $token): User
    {
        $user = User::where('github_id', $githubUser->getId())->first();

        if ($user) {
            $user->update([
                'github_token' => $token,
                'github_nickname' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            return $user;
        }

        $user = User::where('email', $githubUser->getEmail())->first();

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                throw new UnverifiedOAuthEmailException;
            }

            $user->update([
                'github_id' => $githubUser->getId(),
                'github_token' => $token,
                'github_nickname' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            return $user;
        }

        return User::create([
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'email' => $githubUser->getEmail(),
            'github_id' => $githubUser->getId(),
            'github_token' => $token,
            'github_nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => null,
        ]);
    }
}
