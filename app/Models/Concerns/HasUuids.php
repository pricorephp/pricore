<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * Refuse a route value that is not a UUID before it reaches the database.
     *
     * Postgres types these columns as `uuid` and rejects malformed input outright,
     * which surfaces as a 500 and aborts the surrounding transaction. SQLite and
     * MySQL compare them as strings and simply match nothing, so the missing check
     * is invisible there. Either way the answer should be 404.
     *
     * @param  Builder  $query
     * @param  mixed  $value
     * @return Builder
     *
     * @throws ModelNotFoundException
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === $this->getKeyName() && ! Str::isUuid($value)) {
            throw (new ModelNotFoundException)->setModel(static::class, [$value]);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
