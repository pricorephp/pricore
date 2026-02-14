<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use App\Models\Pivots\OrganizationUserPivot;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $github_id
 * @property string|null $github_token
 * @property string|null $github_nickname
 * @property string|null $avatar_url
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrganizationUserPivot|null $pivot
 * @property-read Collection<int, AccessToken> $accessTokens
 * @property-read int|null $access_tokens_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Organization> $organizations
 * @property-read int|null $organizations_count
 * @property-read Collection<int, UserGitCredential> $gitCredentials
 * @property-read int|null $git_credentials_count
 * @property-read Collection<int, Organization> $ownedOrganizations
 * @property-read int|null $owned_organizations_count
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static Builder<static>|User whereTwoFactorSecret($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereUuid($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $guarded = ['uuid'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'github_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_uuid', 'uuid');
    }

    /**
     * @return BelongsToMany<Organization, $this, OrganizationUserPivot, 'pivot'>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users', 'user_uuid', 'organization_uuid', 'uuid', 'uuid')
            ->using(OrganizationUserPivot::class)
            ->withPivot('role', 'uuid', 'last_accessed_at')
            ->withTimestamps();
    }

    public function lastAccessedOrganization(): ?Organization
    {
        return $this->organizations()
            ->orderByDesc('organization_users.last_accessed_at')
            ->first();
    }

    /**
     * @return HasMany<AccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class, 'user_uuid', 'uuid');
    }

    /**
     * @return HasMany<UserGitCredential, $this>
     */
    public function gitCredentials(): HasMany
    {
        return $this->hasMany(UserGitCredential::class, 'user_uuid', 'uuid');
    }

    public function hasGitHubConnected(): bool
    {
        return $this->github_id !== null && $this->github_token !== null;
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function githubToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }
}
