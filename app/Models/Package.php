<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property string|null $repository_uuid
 * @property string $name
 * @property string|null $description
 * @property string|null $type
 * @property string $visibility
 * @property bool $is_proxy
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Repository|null $repository
 * @property-read Collection<int, PackageVersion> $versions
 * @property-read int|null $versions_count
 *
 * @method static PackageFactory factory($count = null, $state = [])
 * @method static Builder<static>|Package newModelQuery()
 * @method static Builder<static>|Package newQuery()
 * @method static Builder<static>|Package query()
 * @method static Builder<static>|Package whereCreatedAt($value)
 * @method static Builder<static>|Package whereDescription($value)
 * @method static Builder<static>|Package whereIsProxy($value)
 * @method static Builder<static>|Package whereName($value)
 * @method static Builder<static>|Package whereOrganizationUuid($value)
 * @method static Builder<static>|Package whereRepositoryUuid($value)
 * @method static Builder<static>|Package whereType($value)
 * @method static Builder<static>|Package whereUpdatedAt($value)
 * @method static Builder<static>|Package whereUuid($value)
 * @method static Builder<static>|Package whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class Package extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'is_proxy' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository_uuid', 'uuid');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class, 'package_uuid', 'uuid');
    }
}
