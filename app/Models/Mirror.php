<?php

namespace App\Models;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Concerns\HasUuids;
use Database\Factories\MirrorFactory;
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
 * @property string $url
 * @property MirrorAuthType $auth_type
 * @property array<string, mixed>|null $auth_credentials
 * @property bool $mirror_dist
 * @property RepositorySyncStatus|null $sync_status
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Package> $packages
 * @property-read int|null $packages_count
 * @property-read Collection<int, MirrorSyncLog> $syncLogs
 * @property-read int|null $sync_logs_count
 *
 * @method static Builder<static>|Mirror newModelQuery()
 * @method static Builder<static>|Mirror newQuery()
 * @method static Builder<static>|Mirror query()
 *
 * @mixin Eloquent
 */
class Mirror extends Model
{
    /** @use HasFactory<MirrorFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'auth_type' => MirrorAuthType::class,
        'auth_credentials' => 'encrypted:array',
        'mirror_dist' => 'boolean',
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
        return $this->hasMany(Package::class, 'mirror_uuid', 'uuid');
    }

    /**
     * @return HasMany<MirrorSyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(MirrorSyncLog::class, 'mirror_uuid', 'uuid');
    }
}
