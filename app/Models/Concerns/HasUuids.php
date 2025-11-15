<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuids
{
    protected static function bootHasUuids(): void
    {
        static::creating(function ($model) {
            if ($model->uuid) {
                return;
            }

            $model->uuid = (string) Str::uuid();
        });
    }

    public function getKeyName(): string
    {
        return 'uuid';
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
