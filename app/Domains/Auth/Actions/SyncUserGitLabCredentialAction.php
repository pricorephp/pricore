<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;

class SyncUserGitLabCredentialAction
{
    public function __construct(
        protected SyncUserOAuthCredentialAction $syncUserOAuthCredentialAction,
    ) {}

    public function handle(User $user, string $token, string $url, string $accountId, ?string $accountName = null): bool
    {
        return $this->syncUserOAuthCredentialAction->handle(
            user: $user,
            provider: GitProvider::GitLab,
            credentials: ['token' => $token, 'url' => $url],
            accountId: $accountId,
            accountName: $accountName,
        );
    }
}
