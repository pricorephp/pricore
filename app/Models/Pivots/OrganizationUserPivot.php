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
 */
class OrganizationUserPivot extends Pivot
{
    protected $table = 'organization_users';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
