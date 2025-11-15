<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\PackageVersionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $package_uuid
 * @property string $version
 * @property string $normalized_version
 * @property array<array-key, mixed> $composer_json
 * @property string|null $source_url
 * @property string|null $source_reference
 * @property string|null $dist_url
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Package $package
 *
 * @method static PackageVersionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PackageVersion newModelQuery()
 * @method static Builder<static>|PackageVersion newQuery()
 * @method static Builder<static>|PackageVersion query()
 * @method static Builder<static>|PackageVersion whereComposerJson($value)
 * @method static Builder<static>|PackageVersion whereCreatedAt($value)
 * @method static Builder<static>|PackageVersion whereDistUrl($value)
 * @method static Builder<static>|PackageVersion whereNormalizedVersion($value)
 * @method static Builder<static>|PackageVersion wherePackageUuid($value)
 * @method static Builder<static>|PackageVersion whereReleasedAt($value)
 * @method static Builder<static>|PackageVersion whereSourceReference($value)
 * @method static Builder<static>|PackageVersion whereSourceUrl($value)
 * @method static Builder<static>|PackageVersion whereUpdatedAt($value)
 * @method static Builder<static>|PackageVersion whereUuid($value)
 * @method static Builder<static>|PackageVersion whereVersion($value)
 *
 * @mixin Eloquent
 */
class PackageVersion extends Model
{
    /** @use HasFactory<PackageVersionFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'composer_json' => 'array',
        'released_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }
}
