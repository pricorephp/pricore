<?php

namespace App\Domains\Token\Contracts\Data;

use App\Models\AccessToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccessTokenData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?array $scopes,
        public ?string $lastUsedAt,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}

    public static function fromModel(AccessToken $token): self
    {
        return new self(
            uuid: $token->uuid,
            name: $token->name ?? '',
            scopes: $token->scopes,
            lastUsedAt: $token->last_used_at?->toIso8601String(),
            expiresAt: $token->expires_at?->toIso8601String(),
            createdAt: $token->created_at->toIso8601String(),
        );
    }
}
