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

    public function handle(
        ?Organization $organization,
        ?User $user,
        string $name,
        ?Carbon $expiresAt = null,
        bool $canPublish = false,
    ): TokenCreatedData {
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $accessToken = AccessToken::create([
            'organization_uuid' => $organization?->uuid,
            'user_uuid' => $user?->uuid,
            'name' => $name,
            'token_hash' => $tokenHash,
            'scopes' => $canPublish
                ? [TokenScope::Read->value, TokenScope::Write->value]
                : [TokenScope::Read->value],
            'expires_at' => $expiresAt,
        ]);

        if ($organization) {
            $this->recordActivity->handle(
                organization: $organization,
                type: ActivityType::TokenCreated,
                subject: $accessToken,
                actor: $user ?? auth()->user(),
                properties: array_filter(['name' => $name, 'can_publish' => $canPublish]),
            );
        }

        return new TokenCreatedData(
            plainToken: $plainToken,
            name: $name,
            expiresAt: $accessToken->expires_at,
            organizationUuid: $accessToken->organization_uuid,
        );
    }
}
