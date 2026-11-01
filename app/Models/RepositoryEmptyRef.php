<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A ref that held no composer.json at its commit when last synced. It yields
 * no package version to compare against, so this is what lets the next sync
 * skip it while the commit stays the same.
 *
 * @property string $uuid
 * @property string $repository_uuid
 * @property string $name
 * @property string $commit
 * @property Carbon $checked_at
 * @property-read Repository $repository
 *
 * @method static Builder<static>|RepositoryEmptyRef newModelQuery()
 * @method static Builder<static>|RepositoryEmptyRef newQuery()
 * @method static Builder<static>|RepositoryEmptyRef query()
 *
 * @mixin \Eloquent
 */
class RepositoryEmptyRef extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['uuid'];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository_uuid', 'uuid');
    }
}
