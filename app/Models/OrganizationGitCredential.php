<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Concerns\HasUuids;
use Database\Factories\OrganizationGitCredentialFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property GitProvider $provider
 * @property array<string, mixed> $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 *
 * @method static OrganizationGitCredentialFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrganizationGitCredential newModelQuery()
 * @method static Builder<static>|OrganizationGitCredential newQuery()
 * @method static Builder<static>|OrganizationGitCredential query()
 * @method static Builder<static>|OrganizationGitCredential whereCreatedAt($value)
 * @method static Builder<static>|OrganizationGitCredential whereCredentials($value)
 * @method static Builder<static>|OrganizationGitCredential whereOrganizationUuid($value)
 * @method static Builder<static>|OrganizationGitCredential whereProvider($value)
 * @method static Builder<static>|OrganizationGitCredential whereUpdatedAt($value)
 * @method static Builder<static>|OrganizationGitCredential whereUuid($value)
 *
 * @mixin Eloquent
 */
class OrganizationGitCredential extends Model
{
    /** @use HasFactory<OrganizationGitCredentialFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'provider' => GitProvider::class,
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * Get the credentials attribute with encryption.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode(decrypt($value), true) : [],
            set: fn (array $value) => encrypt(json_encode($value)),
        );
    }
}
