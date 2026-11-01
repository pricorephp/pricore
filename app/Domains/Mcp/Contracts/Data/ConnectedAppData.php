<?php

namespace App\Domains\Mcp\Contracts\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ConnectedAppData extends Data
{
    public function __construct(
        public string $clientId,
        public string $name,
        public ?CarbonInterface $authorizedAt,
        public ?CarbonInterface $lastActiveAt,
    ) {}
}
