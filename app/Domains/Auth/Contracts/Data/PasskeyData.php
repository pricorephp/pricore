<?php

namespace App\Domains\Auth\Contracts\Data;

use Carbon\CarbonInterface;
use Laravel\Passkeys\Passkey;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PasskeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $authenticator,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $lastUsedAt,
    ) {}

    public static function fromModel(Passkey $passkey): self
    {
        return new self(
            id: $passkey->id,
            name: $passkey->name,
            authenticator: $passkey->authenticator,
            createdAt: $passkey->created_at,
            lastUsedAt: $passkey->last_used_at,
        );
    }
}
