<?php

namespace App\Domains\Repository\Contracts\Enums;

enum RepositorySyncStatus: string
{
    case Ok = 'ok';
    case Failed = 'failed';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Failed => 'Failed',
            self::Pending => 'Pending',
        };
    }

    public function isOk(): bool
    {
        return $this === self::Ok;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
