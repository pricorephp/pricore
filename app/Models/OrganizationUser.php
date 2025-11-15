<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\OrganizationUserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property string $user_uuid
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 *
 * @method static OrganizationUserFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrganizationUser newModelQuery()
 * @method static Builder<static>|OrganizationUser newQuery()
 * @method static Builder<static>|OrganizationUser query()
 * @method static Builder<static>|OrganizationUser whereCreatedAt($value)
 * @method static Builder<static>|OrganizationUser whereOrganizationUuid($value)
 * @method static Builder<static>|OrganizationUser whereRole($value)
 * @method static Builder<static>|OrganizationUser whereUpdatedAt($value)
 * @method static Builder<static>|OrganizationUser whereUserUuid($value)
 * @method static Builder<static>|OrganizationUser whereUuid($value)
 *
 * @mixin \Eloquent
 */
class OrganizationUser extends Model
{
    /** @use HasFactory<OrganizationUserFactory> */
    use HasFactory, HasUuids;

    protected $table = 'organization_users';

    protected $guarded = ['uuid'];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
