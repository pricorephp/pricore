<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\OrganizationSshKey;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationSshKeyData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $publicKey,
        public string $fingerprint,
        public string $createdAt,
    ) {}

    public static function fromModel(OrganizationSshKey $organizationSshKey): self
    {
        return new self(
            uuid: $organizationSshKey->uuid,
            name: $organizationSshKey->name,
            publicKey: $organizationSshKey->public_key,
            fingerprint: $organizationSshKey->fingerprint,
            createdAt: $organizationSshKey->created_at?->toISOString() ?? '',
        );
    }
}
