<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\RepositoryViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $repository_uuid
 * @property int $view_count
 * @property Carbon $last_viewed_at
 * @property-read User $user
 * @property-read Repository $repository
 *
 * @method static RepositoryViewFactory factory($count = null, $state = [])
 * @method static Builder<static>|RepositoryView newModelQuery()
 * @method static Builder<static>|RepositoryView newQuery()
 * @method static Builder<static>|RepositoryView query()
 *
 * @mixin \Eloquent
 */
class RepositoryView extends Model
{
    /** @use HasFactory<RepositoryViewFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $guarded = ['uuid'];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'last_viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository_uuid', 'uuid');
    }
}
