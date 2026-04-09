<?php

namespace App\Domains\Release\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReleaseInfoData extends Data
{
    /**
     * @param  array<int, ReleaseData>  $releases
     */
    public function __construct(
        public ?string $currentVersion,
        public ?string $latestVersion,
        public bool $isOutdated,
        public array $releases,
    ) {}
}
