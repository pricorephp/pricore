<?php

namespace App\Domains\Package\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageFacetsData extends Data
{
    /**
     * @param  array<int, PackageSourceOptionData>  $sources
     */
    public function __construct(
        public int $total,
        public array $sources,
        public int $vulnerable,
        public int $syncFailed,
    ) {}
}
