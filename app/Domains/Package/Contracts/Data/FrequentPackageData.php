<?php

namespace App\Domains\Package\Contracts\Data;

use App\Models\Package;
use App\Models\PackageVersion;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FrequentPackageData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $latestVersion,
    ) {}

    public static function fromModel(Package $package): self
    {
        /** @var PackageVersion|null $latestVersion */
        $latestVersion = $package->versions()
            ->stable()
            ->orderBySemanticVersion('desc')
            ->first();

        return new self(
            uuid: $package->uuid,
            name: $package->name,
            latestVersion: $latestVersion?->version,
        );
    }
}
