<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Concerns\HasUuids;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $mirror_uuid
 * @property string|null $batch_id
 * @property SyncStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 * @property array<array-key, mixed>|null $details
 * @property int $versions_added
 * @property int $versions_updated
 * @property int $versions_skipped
 * @property int $versions_failed
 * @property int $versions_removed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Mirror $mirror
 *
 * @method static Builder<static>|MirrorSyncLog newModelQuery()
 * @method static Builder<static>|MirrorSyncLog newQuery()
 * @method static Builder<static>|MirrorSyncLog query()
 *
 * @mixin Eloquent
 */
class MirrorSyncLog extends Model
{
    use HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'status' => SyncStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'details' => 'array',
        'versions_added' => 'integer',
        'versions_updated' => 'integer',
        'versions_skipped' => 'integer',
        'versions_failed' => 'integer',
        'versions_removed' => 'integer',
    ];

    /**
     * @return BelongsTo<Mirror, $this>
     */
    public function mirror(): BelongsTo
    {
        return $this->belongsTo(Mirror::class, 'mirror_uuid', 'uuid');
    }
}
