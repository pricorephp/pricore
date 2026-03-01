<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UpdateUserGitLabProfileAction
{
    public function handle(User $user, SocialiteUser $gitlabUser): void
    {
        $user->update([
            'gitlab_id' => $user->gitlab_id ?? $gitlabUser->getId(),
            'gitlab_nickname' => $gitlabUser->getNickname(),
            'avatar_url' => $gitlabUser->getAvatar(),
        ]);
    }
}
