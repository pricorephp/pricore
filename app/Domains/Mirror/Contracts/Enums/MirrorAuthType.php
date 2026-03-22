<?php

namespace App\Domains\Mirror\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MirrorAuthType: string
{
    case None = 'none';
    case Basic = 'basic';
    case Bearer = 'bearer';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Basic => 'HTTP Basic',
            self::Bearer => 'Bearer Token',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::None->value => self::None->label(),
            self::Basic->value => self::Basic->label(),
            self::Bearer->value => self::Bearer->label(),
        ];
    }
}
