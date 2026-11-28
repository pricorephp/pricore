<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;

class SyncUserOAuthCredentialAction
{
    /**
     * Updates the credential of the same provider account on the same instance, or a lone unnamed
     * credential from before accounts were tracked, and creates one otherwise. Named credentials
     * without an account were added by hand and are never taken over.
     *
     * @param  array<string, mixed>  $credentials
     * @return bool Whether an existing credential was updated
     */
    public function handle(
        User $user,
        GitProvider $provider,
        array $credentials,
        string $accountId,
        ?string $accountName = null,
    ): bool {
        $accountName = $accountName !== null && $accountName !== '' ? $accountName : null;

        $sameInstance = $user->gitCredentials()
            ->where('provider', $provider)
            ->oldest()
            ->get()
            ->filter(fn (UserGitCredential $credential) => $credential->servesBaseUrl($credentials['url'] ?? null));

        $credential = $sameInstance->first(fn (UserGitCredential $credential) => $credential->account_id === $accountId);

        $loneCredential = $sameInstance->count() === 1 ? $sameInstance->first() : null;

        if (! $credential && $loneCredential?->account_id === null && $loneCredential?->name === null) {
            $credential = $loneCredential;
        }

        if ($credential) {
            $credential->update([
                'name' => $credential->name ?? $accountName,
                'account_id' => $accountId,
                'credentials' => $credentials,
            ]);

            return true;
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => $provider,
            'name' => $accountName,
            'account_id' => $accountId,
            'credentials' => $credentials,
        ]);

        return false;
    }
}
