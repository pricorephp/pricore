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

        if ($driver === 'sqlite') {
            // SQLite: Extract major, minor, patch from normalized_version and sort numerically
            return $query->orderByRaw(
                "CAST(SUBSTR(normalized_version, 1, INSTR(normalized_version || '.', '.') - 1) AS INTEGER) {$direction}, ".
                "CAST(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1) || '.', '.') - 1) AS INTEGER) {$direction}, ".
                "CAST(SUBSTR(normalized_version, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1), '.') + INSTR(normalized_version, '.') + 1, INSTR(SUBSTR(normalized_version, INSTR(SUBSTR(normalized_version, INSTR(normalized_version, '.') + 1), '.') + INSTR(normalized_version, '.') + 1) || '.', '.') - 1) AS INTEGER) {$direction}"
            );
        }

        // MySQL/MariaDB: Use SUBSTRING_INDEX
        if (in_array($driver, ['mysql', 'mariadb'])) {
            return $query->orderByRaw(
                "CAST(SUBSTRING_INDEX(normalized_version, '.', 1) AS UNSIGNED) {$direction}, ".
                "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(normalized_version, '.', 2), '.', -1) AS UNSIGNED) {$direction}, ".
                "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(normalized_version, '.', 3), '.', -1) AS UNSIGNED) {$direction}"
            );
        }

        // PostgreSQL: Use SPLIT_PART
        if ($driver === 'pgsql') {
            return $query->orderByRaw(
                "CAST(SPLIT_PART(normalized_version, '.', 1) AS INTEGER) {$direction}, ".
                "CAST(SPLIT_PART(normalized_version, '.', 2) AS INTEGER) {$direction}, ".
                "CAST(SPLIT_PART(normalized_version, '.', 3) AS INTEGER) {$direction}"
            );
        }

        // Fallback: simple string sort
        return $query->orderBy('normalized_version', $direction);
    }
}
