<?php

namespace App\Models;

use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\Concerns\HasUuids;
use Database\Factories\SecurityAdvisoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $advisory_id
 * @property string $package_name
 * @property string $title
 * @property string|null $link
 * @property string|null $cve
 * @property string $affected_versions
 * @property AdvisorySeverity $severity
 * @property array<int, array{name: string, remoteId: string}>|null $sources
 * @property Carbon|null $reported_at
 * @property string|null $composer_repository
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SecurityAdvisoryMatch> $matches
 *
 * @method static SecurityAdvisoryFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SecurityAdvisory extends Model
{
    /** @use HasFactory<SecurityAdvisoryFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'sources' => 'array',
        'reported_at' => 'datetime',
        'severity' => AdvisorySeverity::class,
    ];

    /**
     * @return HasMany<SecurityAdvisoryMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(SecurityAdvisoryMatch::class, 'security_advisory_uuid', 'uuid');
    }
}
