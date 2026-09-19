<?php

namespace App\Domains\Search\Contracts\Data;

use App\Models\Organization;
use App\Models\Package;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SearchPackageData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public string $organizationName,
        public string $organizationSlug,
    ) {}

    public static function fromModel(Package $package, Organization $organization): self
    {
        return new self(
            uuid: $package->uuid,
            name: $package->name,
            description: $package->description,
            organizationName: $organization->name,
            organizationSlug: $organization->slug,
        );
    }
}
