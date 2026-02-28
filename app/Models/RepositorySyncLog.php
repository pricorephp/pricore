<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Concerns\HasUuids;
use Database\Factories\RepositorySyncLogFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $repository_uuid
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
 * @property-read Repository $repository
 *
 * @method static \Database\Factories\RepositorySyncLogFactory factory($count = null, $state = [])
 * @method static Builder<static>|RepositorySyncLog newModelQuery()
 * @method static Builder<static>|RepositorySyncLog newQuery()
 * @method static Builder<static>|RepositorySyncLog query()
 * @method static Builder<static>|RepositorySyncLog whereCompletedAt($value)
 * @method static Builder<static>|RepositorySyncLog whereCreatedAt($value)
 * @method static Builder<static>|RepositorySyncLog whereDetails($value)
 * @method static Builder<static>|RepositorySyncLog whereErrorMessage($value)
 * @method static Builder<static>|RepositorySyncLog whereRepositoryUuid($value)
 * @method static Builder<static>|RepositorySyncLog whereStartedAt($value)
 * @method static Builder<static>|RepositorySyncLog whereStatus($value)
 * @method static Builder<static>|RepositorySyncLog whereUpdatedAt($value)
 * @method static Builder<static>|RepositorySyncLog whereUuid($value)
 * @method static Builder<static>|RepositorySyncLog whereVersionsAdded($value)
 * @method static Builder<static>|RepositorySyncLog whereVersionsUpdated($value)
 *
 * @mixin Eloquent
 */
class RepositorySyncLog extends Model
{
    /** @use HasFactory<RepositorySyncLogFactory> */
    use HasFactory, HasUuids;

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
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository_uuid', 'uuid');
    }
}
