<?php

namespace App\Domains\Token\Contracts\Data;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccessTokenData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public bool $canPublish,
        public ?CarbonInterface $lastUsedAt,
        public ?CarbonInterface $expiresAt,
        public CarbonInterface $createdAt,
    ) {}

    public static function fromModel(AccessToken $token): self
    {
        $createdAt = $token->created_at;

        if ($createdAt === null) {
            throw new \RuntimeException('AccessToken created_at cannot be null');
        }

        return new self(
            uuid: $token->uuid,
            name: $token->name ?? '',
            canPublish: $token->hasScope(TokenScope::Write),
            lastUsedAt: $token->last_used_at,
            expiresAt: $token->expires_at,
            createdAt: $createdAt,
        );
    }
}
