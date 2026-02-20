<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RecentReleaseData extends Data
{
    public function __construct(
        public string $packageName,
        public string $packageUuid,
        public string $version,
        public bool $isStable,
        public ?CarbonInterface $releasedAt,
    ) {}

    public static function fromModel(PackageVersion $version): self
    {
        $isStable = ! str_contains($version->version, 'dev')
            && (bool) preg_match('/^\d+\.\d+/', $version->version);

        return new self(
            packageName: $version->package->name,
            packageUuid: $version->package->uuid,
            version: $version->version,
            isStable: $isStable,
            releasedAt: $version->released_at,
        );
    }
}
