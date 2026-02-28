<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SharedData extends Data
{
    public function __construct(
        public string $name,
        public ?string $version,
        public AuthData $auth,
        public ?SearchData $search,
        public bool $sidebarOpen,
        public ?FlashData $flash,
    ) {}
}
