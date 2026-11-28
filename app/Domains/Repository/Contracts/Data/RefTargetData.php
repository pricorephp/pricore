<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

/**
 * What a ref releases: one version for every package in the repository, or,
 * for a tag in the repository's tag format, one version for a single package.
 */
class RefTargetData extends Data
{
    public function __construct(
        public string $version,
        public ?string $packageKey = null,
    ) {}

    public function isShared(): bool
    {
        return $this->packageKey === null;
    }
}
