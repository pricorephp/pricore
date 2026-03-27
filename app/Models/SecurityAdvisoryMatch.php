<?php

namespace App\Models;

use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Models\Concerns\HasUuids;
use Database\Factories\SecurityAdvisoryMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $security_advisory_uuid
 * @property string $package_version_uuid
 * @property AdvisoryMatchType $match_type
 * @property string|null $dependency_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SecurityAdvisory $advisory
 * @property-read PackageVersion $packageVersion
 *
 * @method static SecurityAdvisoryMatchFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SecurityAdvisoryMatch extends Model
{
    /** @use HasFactory<SecurityAdvisoryMatchFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'match_type' => AdvisoryMatchType::class,
    ];

    /**
     * @return BelongsTo<SecurityAdvisory, $this>
     */
    public function advisory(): BelongsTo
    {
        return $this->belongsTo(SecurityAdvisory::class, 'security_advisory_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<PackageVersion, $this>
     */
    public function packageVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class, 'package_version_uuid', 'uuid');
    }
}
