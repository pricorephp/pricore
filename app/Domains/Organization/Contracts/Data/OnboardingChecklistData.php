<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OnboardingChecklistData extends Data
{
    public function __construct(
        public bool $hasRepository,
        public bool $hasPersonalToken,
        public bool $hasOrgToken,
        public bool $isDismissed,
    ) {}
}
