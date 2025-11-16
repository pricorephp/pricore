<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property string $user_uuid
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereOrganizationUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereUserUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationUserPivot whereUuid($value)
 *
 * @mixin \Eloquent
 */
class OrganizationUserPivot extends Pivot
{
    protected $table = 'organization_users';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
