<?php

namespace App\Domains\Security\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AdvisoryMatchType: string
{
    case Direct = 'direct';
    case Dependency = 'dependency';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Dependency => 'Dependency',
        };
    }

    public function isDirect(): bool
    {
        return $this === self::Direct;
    }

    public function isDependency(): bool
    {
        return $this === self::Dependency;
    }
}
