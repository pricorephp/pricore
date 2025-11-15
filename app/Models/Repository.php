<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Concerns\HasUuids;
use Database\Factories\RepositoryFactory;
use Eloquent;
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
 * @property string $name
 * @property GitProvider $provider
 * @property string $repo_identifier
 * @property string|null $default_branch
 * @property Carbon|null $last_synced_at
 * @property RepositorySyncStatus|null $sync_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Package> $packages
 * @property-read int|null $packages_count
 * @property-read Collection<int, RepositorySyncLog> $syncLogs
 * @property-read int|null $sync_logs_count
 *
 * @method static RepositoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|Repository newModelQuery()
 * @method static Builder<static>|Repository newQuery()
 * @method static Builder<static>|Repository query()
 * @method static Builder<static>|Repository whereCreatedAt($value)
 * @method static Builder<static>|Repository whereDefaultBranch($value)
 * @method static Builder<static>|Repository whereLastSyncedAt($value)
 * @method static Builder<static>|Repository whereName($value)
 * @method static Builder<static>|Repository whereOrganizationUuid($value)
 * @method static Builder<static>|Repository whereProvider($value)
 * @method static Builder<static>|Repository whereRepoIdentifier($value)
 * @method static Builder<static>|Repository whereSyncStatus($value)
 * @method static Builder<static>|Repository whereUpdatedAt($value)
 * @method static Builder<static>|Repository whereUuid($value)
 *
 * @mixin Eloquent
 */
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'provider' => GitProvider::class,
        'sync_status' => RepositorySyncStatus::class,
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'repository_uuid', 'uuid');
    }

    /**
     * @return HasMany<RepositorySyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(RepositorySyncLog::class, 'repository_uuid', 'uuid');
    }
}
