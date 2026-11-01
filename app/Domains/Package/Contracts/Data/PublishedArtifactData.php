<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Package\Contracts\Enums\ArtifactPublishResult;
use App\Models\Package;
use App\Models\PackageVersion;

class PublishedArtifactData
{
    public function __construct(
        public Package $package,
        public PackageVersion $version,
        public ArtifactPublishResult $result,
        public bool $packageCreated,
    ) {}
}
