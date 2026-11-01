<?php

namespace App\Domains\Token\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Token\Contracts\Data\TokenCreatedData;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateAccessTokenAction
{
    public function __construct(
        protected RecordActivityTask $recordActivity,
    ) {}

    /**
     * @param  array<int, TokenScope|string>  $scopes
     */
    public function handle(
        ?Organization $organization,
        ?User $user,
        string $name,
        ?Carbon $expiresAt = null,
        array $scopes = [TokenScope::Composer],
        ?User $actor = null,
    ): TokenCreatedData {
        $scopes = TokenScope::normalize($scopes);
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $accessToken = AccessToken::create([
            'organization_uuid' => $organization?->uuid,
            'user_uuid' => $user?->uuid,
            'name' => $name,
            'token_hash' => $tokenHash,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        if ($organization) {
            $this->recordActivity->handle(
                organization: $organization,
                type: ActivityType::TokenCreated,
                subject: $accessToken,
                actor: $actor ?? $user ?? auth()->user(),
                properties: array_filter([
                    'name' => $name,
                    'can_publish' => in_array(TokenScope::WritePackages->value, $scopes, true),
                ]),
            );
        }

        return new TokenCreatedData(
            plainToken: $plainToken,
            name: $name,
            expiresAt: $accessToken->expires_at,
            organizationUuid: $accessToken->organization_uuid,
            scopes: $scopes,
        );
    }
}
