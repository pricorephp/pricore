<?php

namespace App\Domains\Organization\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationMemberData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $email,
        public string $role,
        public string $joinedAt,
    ) {}
}
