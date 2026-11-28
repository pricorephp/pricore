<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\UserGitCredential;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GitCredentialData extends Data
{
    public function __construct(
        public string $uuid,
        public string $provider,
        public string $providerLabel,
        public ?string $name,
        public string $label,
        public ?string $baseUrl,
        public bool $isConfigured,
        public int $repositoriesCount,
    ) {}

    public static function fromModel(UserGitCredential $credential): self
    {
        return new self(
            uuid: $credential->uuid,
            provider: $credential->provider->value,
            providerLabel: $credential->provider->label(),
            name: $credential->name,
            label: $credential->label(),
            baseUrl: $credential->baseUrl(),
            isConfigured: ! empty($credential->credentials),
            repositoriesCount: $credential->repositories_count ?? 0,
        );
    }
}
