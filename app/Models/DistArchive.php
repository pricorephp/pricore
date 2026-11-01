<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\DistArchiveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dist archive file that was written to the dist disk.
 *
 * Branches keep a single package_versions row that every sync moves to the new
 * head, so one version produces many archives over time. Each stays addressable
 * here by its own commit, which is what keeps lock files installable.
 *
 * @property string $uuid
 * @property string $package_uuid
 * @property string $package_version_uuid
 * @property string $source_reference
 * @property string $path
 * @property string|null $shasum
 * @property int|null $size
 * @property Carbon|null $detached_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Package $package
 * @property-read PackageVersion $packageVersion
 *
 * @method static Builder<static>|DistArchive current()
 * @method static Builder<static>|DistArchive detached()
 * @method static DistArchiveFactory factory($count = null, $state = [])
 * @method static Builder<static>|DistArchive newModelQuery()
 * @method static Builder<static>|DistArchive newQuery()
 * @method static Builder<static>|DistArchive query()
 *
 * @mixin \Eloquent
 */
class DistArchive extends Model
{
    /** @use HasFactory<DistArchiveFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected function casts(): array
    {
        return [
            'detached_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<PackageVersion, $this>
     */
    public function packageVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class, 'package_version_uuid', 'uuid');
    }

    /**
     * The archive the version currently resolves to.
     *
     * @param  Builder<DistArchive>  $query
     * @return Builder<DistArchive>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('detached_at');
    }

    /**
     * Archives their version has moved past. Kept on disk so lock files pinning
     * the older commit stay installable.
     *
     * @param  Builder<DistArchive>  $query
     * @return Builder<DistArchive>
     */
    public function scopeDetached(Builder $query): Builder
    {
        return $query->whereNotNull('detached_at');
    }
}
