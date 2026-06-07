<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;

class SyncUserGitLabCredentialAction
{
    public function handle(User $user, string $token, string $url): bool
    {
        $credentials = ['token' => $token, 'url' => $url];

        $credential = $user->gitCredentials()->where('provider', GitProvider::GitLab)->first();

        if ($credential) {
            $credential->update([
                'credentials' => $credentials,
            ]);

            return true;
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => GitProvider::GitLab,
            'credentials' => $credentials,
        ]);

        return false;
    }
}
