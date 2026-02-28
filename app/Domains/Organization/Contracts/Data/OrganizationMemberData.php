<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Pivots\OrganizationUserPivot;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationMemberData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $email,
        public ?string $avatar,
        public OrganizationRole $role,
        public ?CarbonInterface $joinedAt,
    ) {}

    public static function fromUserAndPivot(User $user, OrganizationUserPivot $pivot): self
    {
        return new self(
            uuid: $pivot->uuid,
            name: $user->name,
            email: $user->email,
            avatar: $user->avatar,
            role: $pivot->role,
            joinedAt: $pivot->created_at,
        );
    }
}
