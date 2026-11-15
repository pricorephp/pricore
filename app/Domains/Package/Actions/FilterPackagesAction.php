<?php

namespace App\Domains\Package\Actions;

use App\Domains\Package\Contracts\Data\PackageFiltersData;
use App\Domains\Package\Contracts\Enums\PackageSource;
use App\Domains\Package\Contracts\Enums\PackageStatus;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;

class FilterPackagesAction
{
    /**
     * @return Builder<Package>
     */
    public function handle(Organization $organization, PackageFiltersData $packageFiltersData): Builder
    {
        return $organization->packages()
            ->getQuery()
            ->when($packageFiltersData->query !== '', fn (Builder $query) => $this->applySearch(
                $query,
                $packageFiltersData->query,
            ))
            ->when($packageFiltersData->source, fn (Builder $query, PackageSource $source) => $this->applySource(
                $query,
                $source,
                $packageFiltersData->mirror,
            ))
            ->when($packageFiltersData->status, fn (Builder $query, PackageStatus $status) => $this->applyStatus(
                $query,
                $status,
            ));
    }

    /**
     * Matches the search as literal text: `_` and `%` are valid in package
     * names and would otherwise act as LIKE wildcards.
     *
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw("name {$operator} ? escape '!'", [$pattern])
            ->orWhereRaw("description {$operator} ? escape '!'", [$pattern]));
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function applySource(Builder $query, PackageSource $source, ?string $mirror = null): Builder
    {
        return match ($source) {
            PackageSource::Repository => $query->whereNotNull('repository_uuid'),
            PackageSource::Upload => $query->where('is_artifact', true),
            PackageSource::Mirror => $mirror !== null
                ? $query->where('mirror_uuid', $mirror)
                : $query->whereNotNull('mirror_uuid'),
        };
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function applyStatus(Builder $query, PackageStatus $status): Builder
    {
        return match ($status) {
            PackageStatus::Vulnerable => $query->whereHas('advisoryMatches'),
            PackageStatus::SyncFailed => $query->where(fn (Builder $query) => $query
                ->whereRelation('repository', 'sync_status', RepositorySyncStatus::Failed)
                ->orWhereRelation('mirror', 'sync_status', RepositorySyncStatus::Failed)),
        };
    }
}
