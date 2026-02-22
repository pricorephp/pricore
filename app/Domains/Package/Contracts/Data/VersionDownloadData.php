<?php

namespace App\Domains\Package\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class VersionDownloadData extends Data
{
    public function __construct(
        public string $version,
        public int $downloads,
    ) {}
}
