<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $last_synced_at
 * @property int|null $last_updated_since
 * @property int $advisories_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class AdvisorySyncMetadata extends Model
{
    protected $table = 'advisory_sync_metadata';

    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
