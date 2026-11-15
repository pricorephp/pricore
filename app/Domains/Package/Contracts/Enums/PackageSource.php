<?php

namespace App\Domains\Package\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PackageSource: string
{
    case Repository = 'repository';
    case Mirror = 'mirror';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Repository => 'Repositories',
            self::Mirror => 'Mirrors',
            self::Upload => 'Uploads',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Repository->value => self::Repository->label(),
            self::Mirror->value => self::Mirror->label(),
            self::Upload->value => self::Upload->label(),
        ];
    }

    public function isMirror(): bool
    {
        return $this === self::Mirror;
    }
}
