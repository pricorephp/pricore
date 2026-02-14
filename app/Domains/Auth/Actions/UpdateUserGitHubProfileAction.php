<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UpdateUserGitHubProfileAction
{
    public function handle(User $user, SocialiteUser $githubUser, string $token): void
    {
        $user->update([
            'github_token' => $token,
            'github_id' => $user->github_id ?? $githubUser->getId(),
            'github_nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
        ]);
    }
}
