<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;

class SyncUserGitHubCredentialAction
{
    public function handle(User $user, string $token): bool
    {
        $credential = $user->gitCredentials()->where('provider', GitProvider::GitHub)->first();

        if ($credential) {
            $credential->update([
                'credentials' => ['token' => $token],
            ]);

            return true;
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => GitProvider::GitHub,
            'credentials' => ['token' => $token],
        ]);

        return false;
    }
}
