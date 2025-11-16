<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\Organization;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationStatsData extends Data
{
    public function __construct(
        public int $packagesCount,
        public int $repositoriesCount,
        public int $tokensCount,
    ) {}

    public static function fromModel(Organization $organization): self
    {
        return new self(
            packagesCount: $organization->packages()->count(),
            repositoriesCount: $organization->repositories()->count(),
            tokensCount: $organization->accessTokens()->count(),
        );
    }
}
