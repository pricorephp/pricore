<?php

namespace App\Domains\Token\Actions;

use App\Domains\Token\Contracts\Data\TokenCreatedData;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateAccessTokenAction
{
    public function handle(
        ?Organization $organization,
        ?User $user,
        string $name,
        ?Carbon $expiresAt = null
    ): TokenCreatedData {
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $accessToken = AccessToken::create([
            'organization_uuid' => $organization?->uuid,
            'user_uuid' => $user?->uuid,
            'name' => $name,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return new TokenCreatedData(
            plainToken: $plainToken,
            accessToken: $accessToken,
        );
    }
}
