<?php

namespace App\Domains\Token\Contracts\Enums;

enum TokenScope: string
{
    case Read = 'read';
    case Write = 'write';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read packages',
            self::Write => 'Publish packages',
        };
    }
}
