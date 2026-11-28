<?php

namespace App\Domains\Repository\Contracts\Data;

use App\Domains\Repository\Contracts\Enums\TagRelease;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TagFormatPreviewData extends Data
{
    public function __construct(
        public string $tag,
        public TagRelease $release,
        public ?string $version,
        /** The Composer name, or the value from the tag for a package not synced yet */
        public ?string $package,
        public bool $packageKnown,
    ) {}
}
