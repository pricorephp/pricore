<?php

namespace App\Domains\Package\Actions;

use App\Domains\Package\Contracts\Data\PackageFacetsData;
use App\Domains\Package\Contracts\Data\PackageSourceOptionData;
use App\Domains\Package\Contracts\Enums\PackageSource;
use App\Domains\Package\Contracts\Enums\PackageStatus;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;

class BuildPackageFacetsAction
{
    public function __construct(
        protected FilterPackagesAction $filterPackagesAction,
    ) {}

    /**
     * Counts cover the whole organization, not the current filters, so the
     * options stay put while the list narrows.
     */
    public function handle(Organization $organization): PackageFacetsData
    {
        $sources = [];

        foreach ([PackageSource::Repository, PackageSource::Upload] as $source) {
            $count = $this->filterPackagesAction->applySource($this->query($organization), $source)->count();

            if ($count > 0) {
                $sources[] = new PackageSourceOptionData($source, null, $source->label(), $count);
            }
        }

        $mirrors = $organization->mirrors()
            ->withCount('packages')
            ->orderBy('name')
            ->get();

        foreach ($mirrors as $mirror) {
            $count = $mirror->packages_count ?? 0;

            if ($count > 0) {
                $sources[] = new PackageSourceOptionData(PackageSource::Mirror, $mirror->uuid, $mirror->name, $count);
            }
        }

        return new PackageFacetsData(
            total: $this->query($organization)->count(),
            sources: $sources,
            vulnerable: $this->filterPackagesAction->applyStatus($this->query($organization), PackageStatus::Vulnerable)->count(),
            syncFailed: $this->filterPackagesAction->applyStatus($this->query($organization), PackageStatus::SyncFailed)->count(),
        );
    }

    /**
     * @return Builder<Package>
     */
    protected function query(Organization $organization): Builder
    {
        return $organization->packages()->getQuery();
    }
}
