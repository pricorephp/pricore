<?php

namespace App\Domains\Search\Contracts\Data;

use App\Models\Repository;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SearchRepositoryData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $provider,
        public string $providerLabel,
        public string $organizationName,
        public string $organizationSlug,
    ) {}

    public static function fromModel(Repository $repository): self
    {
        return new self(
            uuid: $repository->uuid,
            name: $repository->name,
            provider: $repository->provider->value,
            providerLabel: $repository->provider->label(),
            organizationName: $repository->organization->name,
            organizationSlug: $repository->organization->slug,
        );
    }
}
