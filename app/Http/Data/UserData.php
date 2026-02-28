<?php

namespace App\Http\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $email,
        public ?string $avatar,
        public bool $hasPassword,
        public ?string $emailVerifiedAt,
        public bool $twoFactorEnabled,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            uuid: $user->uuid,
            name: $user->name,
            email: $user->email,
            avatar: $user->avatar,
            hasPassword: $user->has_password,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            twoFactorEnabled: $user->two_factor_confirmed_at !== null,
            createdAt: $user->created_at?->toIso8601String(),
            updatedAt: $user->updated_at?->toIso8601String(),
        );
    }
}
