<?php

namespace App\Domains\Token\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\User;

class UpdateAccessTokenAction
{
    public function __construct(
        protected RecordActivityTask $recordActivity,
    ) {}

    /**
     * Update a token's name and (optionally) its scopes. A null $scopes leaves
     * the existing scopes untouched; an array replaces them.
     *
     * @param  array<int, TokenScope|string>|null  $scopes
     */
    public function handle(AccessToken $accessToken, string $name, ?array $scopes = null, ?User $actor = null): AccessToken
    {
        $attributes = ['name' => $name];

        if ($scopes !== null) {
            $attributes['scopes'] = TokenScope::normalize($scopes);
        }

        $accessToken->update($attributes);

        if ($accessToken->organization) {
            $this->recordActivity->handle(
                organization: $accessToken->organization,
                type: ActivityType::TokenUpdated,
                subject: $accessToken,
                actor: $actor ?? auth()->user(),
                properties: ['name' => $accessToken->name],
            );
        }

        return $accessToken;
    }
}
