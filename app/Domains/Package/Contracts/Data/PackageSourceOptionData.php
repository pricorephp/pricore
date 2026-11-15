<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Package\Contracts\Enums\PackageSource;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageSourceOptionData extends Data
{
    public function __construct(
        public PackageSource $source,
        public ?string $mirror,
        public string $label,
        public int $count,
    ) {}
}
