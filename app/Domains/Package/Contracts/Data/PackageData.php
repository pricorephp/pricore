<?php

namespace App\Domains\Package\Contracts\Data;

use App\Models\Package;
use App\Models\PackageVersion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public ?string $type,
        public string $visibility,
        public bool $isProxy,
        public int $versionsCount,
        public ?string $latestVersion,
        public CarbonInterface $updatedAt,
        public ?string $repositoryName,
        public ?string $repositoryIdentifier,
        public ?string $repositoryUuid,
    ) {}

    public static function fromModel(Package $package): self
    {
        /** @var PackageVersion|null $latestVersion */
        $latestVersion = $package->versions()
            ->stable()
            ->orderBySemanticVersion('desc')
            ->first();

        $updatedAt = $package->updated_at;

        if ($updatedAt === null) {
            throw new \RuntimeException('Package updated_at cannot be null');
        }

        return new self(
            uuid: $package->uuid,
            name: $package->name,
            description: $package->description,
            type: $package->type,
            visibility: $package->visibility,
            isProxy: $package->is_proxy,
            versionsCount: $package->versions_count ?? 0,
            latestVersion: $latestVersion?->version,
            updatedAt: $updatedAt,
            repositoryName: $package->repository?->name,
            repositoryIdentifier: $package->repository?->repo_identifier,
            repositoryUuid: $package->repository?->uuid,
        );
    }
}
