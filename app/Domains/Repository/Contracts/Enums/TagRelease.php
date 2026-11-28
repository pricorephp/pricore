<?php

namespace App\Domains\Repository\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a tag releases under a repository's tag format.
 */
#[TypeScript]
enum TagRelease: string
{
    case Package = 'package';
    case Shared = 'shared';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Package => 'One package',
            self::Shared => 'All packages',
            self::Ignored => 'Ignored',
        };
    }
}
