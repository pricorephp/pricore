<?php

namespace App\Domains\Package\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PackageStatus: string
{
    case Vulnerable = 'vulnerable';
    case SyncFailed = 'sync_failed';

    public function label(): string
    {
        return match ($this) {
            self::Vulnerable => 'Advisories',
            self::SyncFailed => 'Sync failed',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Vulnerable->value => self::Vulnerable->label(),
            self::SyncFailed->value => self::SyncFailed->label(),
        ];
    }
}
