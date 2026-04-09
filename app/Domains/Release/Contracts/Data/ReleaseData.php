<?php

namespace App\Domains\Release\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReleaseData extends Data
{
    public function __construct(
        public string $name,
        public string $tagName,
        public string $version,
        public string $htmlUrl,
        public ?string $publishedAt,
        public string $bodyHtml,
    ) {}
}
