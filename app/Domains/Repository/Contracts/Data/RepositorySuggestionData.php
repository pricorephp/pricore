<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RepositorySuggestionData extends Data
{
    public function __construct(
        public string $name,
        public string $fullName,
        public bool $isPrivate,
        public ?string $description,
    ) {}
}
