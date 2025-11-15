<?php

namespace App\Domains\Repository\Contracts\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
