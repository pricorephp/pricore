<?php

namespace App\Domains\Token\Contracts\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TokenCreatedData extends Data
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $plainToken,
        public string $name,
        public ?CarbonInterface $expiresAt,
        public ?string $organizationUuid,
        public array $scopes = [],
    ) {}
}
