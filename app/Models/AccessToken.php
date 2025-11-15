<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\AccessTokenFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string|null $organization_uuid
 * @property string|null $user_uuid
 * @property string|null $name
 * @property string $token_hash
 * @property array<array-key, mixed>|null $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read User|null $user
 *
 * @method static AccessTokenFactory factory($count = null, $state = [])
 * @method static Builder<static>|AccessToken newModelQuery()
 * @method static Builder<static>|AccessToken newQuery()
 * @method static Builder<static>|AccessToken query()
 * @method static Builder<static>|AccessToken whereCreatedAt($value)
 * @method static Builder<static>|AccessToken whereExpiresAt($value)
 * @method static Builder<static>|AccessToken whereLastUsedAt($value)
 * @method static Builder<static>|AccessToken whereName($value)
 * @method static Builder<static>|AccessToken whereOrganizationUuid($value)
 * @method static Builder<static>|AccessToken whereScopes($value)
 * @method static Builder<static>|AccessToken whereTokenHash($value)
 * @method static Builder<static>|AccessToken whereUpdatedAt($value)
 * @method static Builder<static>|AccessToken whereUserUuid($value)
 * @method static Builder<static>|AccessToken whereUuid($value)
 *
 * @mixin Eloquent
 */
class AccessToken extends Model
{
    /** @use HasFactory<AccessTokenFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

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
