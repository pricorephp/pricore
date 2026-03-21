<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\OrganizationSshKeyFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string $public_key
 * @property string $private_key
 * @property string $fingerprint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Repository> $repositories
 * @property-read int|null $repositories_count
 *
 * @method static OrganizationSshKeyFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrganizationSshKey newModelQuery()
 * @method static Builder<static>|OrganizationSshKey newQuery()
 * @method static Builder<static>|OrganizationSshKey query()
 *
 * @mixin Eloquent
 */
class OrganizationSshKey extends Model
{
    /** @use HasFactory<OrganizationSshKeyFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = ['uuid'];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'ssh_key_uuid', 'uuid');
    }

    /**
     * @return Attribute<string, string>
     */
    protected function privateKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : '',
            set: fn (string $value) => encrypt($value),
        );
    }
}
