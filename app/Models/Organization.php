<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use App\Models\Pivots\OrganizationUserPivot;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $owner_uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AccessToken> $accessTokens
 * @property-read int|null $access_tokens_count
 * @property-read Collection<int, OrganizationGitCredential> $gitCredentials
 * @property-read int|null $git_credentials_count
 * @property-read OrganizationUserPivot|null $pivot
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read User $owner
 * @property-read Collection<int, Package> $packages
 * @property-read int|null $packages_count
 * @property-read Collection<int, Repository> $repositories
 * @property-read int|null $repositories_count
 *
 * @method static OrganizationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Organization newModelQuery()
 * @method static Builder<static>|Organization newQuery()
 * @method static Builder<static>|Organization query()
 * @method static Builder<static>|Organization whereCreatedAt($value)
 * @method static Builder<static>|Organization whereName($value)
 * @method static Builder<static>|Organization whereOwnerUuid($value)
 * @method static Builder<static>|Organization whereSlug($value)
 * @method static Builder<static>|Organization whereUpdatedAt($value)
 * @method static Builder<static>|Organization whereUuid($value)
 *
 * @mixin \Eloquent
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['uuid'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    /**
     * @return BelongsToMany<User, $this, OrganizationUserPivot, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users', 'organization_uuid', 'user_uuid', 'uuid', 'uuid')
            ->using(OrganizationUserPivot::class)
            ->withPivot('role', 'uuid')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return HasMany<AccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return HasMany<OrganizationGitCredential, $this>
     */
    public function gitCredentials(): HasMany
    {
        return $this->hasMany(OrganizationGitCredential::class, 'organization_uuid', 'uuid');
    }
}
