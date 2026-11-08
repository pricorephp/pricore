<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Exceptions\UnverifiedOAuthEmailException;
use App\Models\User;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateGitLabUserAction
{
    /**
     * Find an existing user by GitLab ID or email, or create a new one.
     * Linking by email requires a verified email on the existing account.
     *
     * @throws UnverifiedOAuthEmailException
     */
    public function handle(SocialiteUser $gitlabUser, string $token): User
    {
        $user = User::where('gitlab_id', $gitlabUser->getId())->first();

        if ($user) {
            $user->update([
                'gitlab_token' => $token,
                'gitlab_nickname' => $gitlabUser->getNickname(),
                'avatar_url' => $gitlabUser->getAvatar(),
            ]);

            return $user;
        }

        $user = User::where('email', $gitlabUser->getEmail())->first();

        if ($user) {
            if (! $user->hasVerifiedEmail() || ! $this->hasConfirmedEmail($gitlabUser)) {
                throw new UnverifiedOAuthEmailException;
            }

            $user->update([
                'gitlab_id' => $gitlabUser->getId(),
                'gitlab_token' => $token,
                'gitlab_nickname' => $gitlabUser->getNickname(),
                'avatar_url' => $gitlabUser->getAvatar(),
            ]);

            return $user;
        }

        return User::create([
            'name' => $gitlabUser->getName() ?? $gitlabUser->getNickname(),
            'email' => $gitlabUser->getEmail(),
            'gitlab_id' => $gitlabUser->getId(),
            'gitlab_token' => $token,
            'gitlab_nickname' => $gitlabUser->getNickname(),
            'avatar_url' => $gitlabUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => null,
        ]);
    }

    private function hasConfirmedEmail(SocialiteUser $gitlabUser): bool
    {
        return $gitlabUser instanceof AbstractUser
            && filled($gitlabUser->getRaw()['confirmed_at'] ?? null);
    }
}
