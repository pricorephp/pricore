<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\PackageDownloadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property string|null $package_uuid
 * @property string $package_name
 * @property string $version
 * @property Carbon $downloaded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Package|null $package
 *
 * @method static PackageDownloadFactory factory($count = null, $state = [])
 * @method static Builder<static>|PackageDownload newModelQuery()
 * @method static Builder<static>|PackageDownload newQuery()
 * @method static Builder<static>|PackageDownload query()
 *
 * @mixin \Eloquent
 */
class PackageDownload extends Model
{
    /** @use HasFactory<PackageDownloadFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }
}
