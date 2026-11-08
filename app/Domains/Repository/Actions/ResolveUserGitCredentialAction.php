<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;

class ResolveUserGitCredentialAction
{
    /**
     * Without an explicit credential the user's oldest one for the provider is used.
     */
    public function handle(User $user, GitProvider $provider, ?string $credentialUuid = null): ?UserGitCredential
    {
        return $user->gitCredentials()
            ->where('provider', $provider)
            ->when($credentialUuid, fn ($query) => $query->where('uuid', $credentialUuid))
            ->oldest()
            ->first();
    }
}
