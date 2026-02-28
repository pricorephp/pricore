<?php

namespace App\Http\Data;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AuthData extends Data
{
    /**
     * @param  array<int, OrganizationData>  $organizations
     */
    public function __construct(
        public ?UserData $user,
        public array $organizations,
    ) {}
}
