<?php

namespace App\Domains\Mirror\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MirrorSyncMode: string
{
    case Full = 'full';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'All packages',
            self::OnDemand => 'On demand',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Full->value => self::Full->label(),
            self::OnDemand->value => self::OnDemand->label(),
        ];
    }

    public function isOnDemand(): bool
    {
        return $this === self::OnDemand;
    }
}
