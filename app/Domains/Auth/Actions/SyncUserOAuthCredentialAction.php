<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;

class SyncUserOAuthCredentialAction
{
    /**
     * Updates the credential named after the connected account on the same instance, or a
     * lone unnamed one from before multiple credentials per provider, and creates one otherwise.
     *
     * @param  array<string, mixed>  $credentials
     * @return bool Whether an existing credential was updated
     */
    public function handle(User $user, GitProvider $provider, array $credentials, ?string $account = null): bool
    {
        $account = $account !== null && $account !== '' ? $account : null;

        $sameInstance = $user->gitCredentials()
            ->where('provider', $provider)
            ->oldest()
            ->get()
            ->filter(fn (UserGitCredential $credential) => $credential->servesBaseUrl($credentials['url'] ?? null));

        $credential = $account !== null
            ? $sameInstance->first(fn (UserGitCredential $credential) => $credential->name === $account)
            : null;

        if (! $credential && $sameInstance->count() === 1 && $sameInstance->first()?->name === null) {
            $credential = $sameInstance->first();
        }

        if ($credential) {
            $credential->update([
                'name' => $credential->name ?? $account,
                'credentials' => $credentials,
            ]);

            return true;
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => $provider,
            'name' => $account,
            'credentials' => $credentials,
        ]);

        return false;
    }
}
