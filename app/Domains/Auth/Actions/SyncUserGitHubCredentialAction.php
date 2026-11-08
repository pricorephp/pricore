<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;

class SyncUserGitHubCredentialAction
{
    public function __construct(
        protected SyncUserOAuthCredentialAction $syncUserOAuthCredentialAction,
    ) {}

    public function handle(User $user, string $token, string $accountId, ?string $accountName = null): bool
    {
        return $this->syncUserOAuthCredentialAction->handle(
            user: $user,
            provider: GitProvider::GitHub,
            credentials: ['token' => $token],
            accountId: $accountId,
            accountName: $accountName,
        );
    }
}
