<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\PackageViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $package_uuid
 * @property int $view_count
 * @property Carbon $last_viewed_at
 * @property-read User $user
 * @property-read Package $package
 *
 * @method static PackageViewFactory factory($count = null, $state = [])
 * @method static Builder<static>|PackageView newModelQuery()
 * @method static Builder<static>|PackageView newQuery()
 * @method static Builder<static>|PackageView query()
 *
 * @mixin \Eloquent
 */
class PackageView extends Model
{
    /** @use HasFactory<PackageViewFactory> */
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
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }
}
