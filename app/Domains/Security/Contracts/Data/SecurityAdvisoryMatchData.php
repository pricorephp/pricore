<?php

namespace App\Domains\Security\Contracts\Data;

use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Models\SecurityAdvisoryMatch;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SecurityAdvisoryMatchData extends Data
{
    public function __construct(
        public string $uuid,
        public SecurityAdvisoryData $advisory,
        public AdvisoryMatchType $matchType,
        public ?string $dependencyName,
    ) {}

    public static function fromModel(SecurityAdvisoryMatch $match): self
    {
        return new self(
            uuid: $match->uuid,
            advisory: SecurityAdvisoryData::fromModel($match->advisory),
            matchType: $match->match_type,
            dependencyName: $match->dependency_name,
        );
    }
}
