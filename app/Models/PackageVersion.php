<?php

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Database\Factories\PackageVersionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $package_uuid
 * @property string $version
 * @property string $normalized_version
 * @property array<array-key, mixed> $composer_json
 * @property string|null $source_url
 * @property string|null $source_reference
 * @property string|null $dist_url
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Package $package
 *
 * @method static Builder<static>|PackageVersion dev()
 * @method static \Database\Factories\PackageVersionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PackageVersion newModelQuery()
 * @method static Builder<static>|PackageVersion newQuery()
 * @method static Builder<static>|PackageVersion orderBySemanticVersion(string $direction = 'desc')
 * @method static Builder<static>|PackageVersion query()
 * @method static Builder<static>|PackageVersion stable()
 * @method static Builder<static>|PackageVersion whereComposerJson($value)
 * @method static Builder<static>|PackageVersion whereCreatedAt($value)
 * @method static Builder<static>|PackageVersion whereDistUrl($value)
 * @method static Builder<static>|PackageVersion whereNormalizedVersion($value)
 * @method static Builder<static>|PackageVersion wherePackageUuid($value)
 * @method static Builder<static>|PackageVersion whereReleasedAt($value)
 * @method static Builder<static>|PackageVersion whereSourceReference($value)
 * @method static Builder<static>|PackageVersion whereSourceUrl($value)
 * @method static Builder<static>|PackageVersion whereUpdatedAt($value)
 * @method static Builder<static>|PackageVersion whereUuid($value)
 * @method static Builder<static>|PackageVersion whereVersion($value)
 *
 * @mixin Eloquent
 */
class PackageVersion extends Model
{
    /** @use HasFactory<PackageVersionFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'composer_json' => 'array',
        'released_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }

    /**
     * @param  Builder<PackageVersion>  $query
     * @return Builder<PackageVersion>
     */
    public function scopeStable(Builder $query): Builder
    {
        // Only include semantic versions (e.g., 1.0.0, 2.3.4)
        // Semantic versions start with a digit and don't end with -dev
        return $query->whereRaw("SUBSTR(version, 1, 1) BETWEEN '0' AND '9'")
            ->whereNotLike('version', '%-dev');
    }

    /**
     * @param  Builder<PackageVersion>  $query
     * @return Builder<PackageVersion>
     */
    public function scopeDev(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereLike('version', 'dev-%')
                ->orWhereLike('version', '%-dev')
                ->orWhereLike('normalized_version', 'dev-%')
                ->orWhereLike('normalized_version', '%-dev');
        });
    }

    /**
     * Order by semantic version (highest first).
     * Parses normalized_version (format: MAJOR.MINOR.PATCH.BUILD) and sorts numerically.
     *
     * @param  Builder<PackageVersion>  $query
     * @return Builder<PackageVersion>
     */
    public function scopeOrderBySemanticVersion(Builder $query, string $direction = 'desc'): Builder
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        if ($driver === 'sqlite') {
            $isSemver = "normalized_version GLOB '[0-9]*.[0-9]*'";

            $substr1 = "SUBSTR(normalized_version, 1, INSTR(normalized_version || '.', '.') - 1)";
            $substr2 = "SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1) || '.', '.') - 1)";
            $substr3 = "SUBSTR(normalized_version, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1), '.') + INSTR(normalized_version, '.') + 1, INSTR(SUBSTR(normalized_version, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1), '.') + INSTR(normalized_version, '.') + 1) || '.', '.') - 1)";

            $part = fn (string $substr): string => "CASE WHEN {$isSemver} THEN CAST({$substr} AS INTEGER) ELSE NULL END";

            return $query->orderByRaw(
                "({$isSemver}) DESC, ".
                "{$part($substr1)} {$direction}, ".
                "{$part($substr2)} {$direction}, ".
                "{$part($substr3)} {$direction}, ".
                "released_at {$direction}, ".
                "version {$direction}"
            );
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $isSemver = "normalized_version REGEXP '^[0-9]+(\\.[0-9]+){0,3}$'";
            $part = fn (int $index): string => "CASE WHEN {$isSemver} THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(normalized_version, '.', {$index}), '.', -1) AS UNSIGNED) ELSE NULL END";

            return $query->orderByRaw(
                "({$isSemver}) DESC, ".
                "{$part(1)} {$direction}, ".
                "{$part(2)} {$direction}, ".
                "{$part(3)} {$direction}, ".
                "released_at {$direction}, ".
                "version {$direction}"
            );
        }

        if ($driver === 'pgsql') {
            $isSemver = "normalized_version ~ '^[0-9]+(\\.[0-9]+){0,3}$'";
            $part = fn (int $index): string => "CASE WHEN {$isSemver} THEN COALESCE(NULLIF(SPLIT_PART(normalized_version, '.', {$index}), ''), '0')::int ELSE NULL END";

            return $query->orderByRaw(
                "({$isSemver}) DESC, ".
                "{$part(1)} {$direction} NULLS LAST, ".
                "{$part(2)} {$direction} NULLS LAST, ".
                "{$part(3)} {$direction} NULLS LAST, ".
                "released_at {$direction} NULLS LAST, ".
                "version {$direction}"
            );
        }

        // Fallback: simple string sort
        return $query->orderBy('normalized_version', $direction);
    }
}
