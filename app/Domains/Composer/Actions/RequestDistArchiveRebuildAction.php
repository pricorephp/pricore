<?php

namespace App\Domains\Composer\Actions;

use App\Domains\Repository\Jobs\RebuildDistArchiveJob;
use App\Models\DistArchive;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

class RequestDistArchiveRebuildAction
{
    /** Downloads fall back to source this long after a failed rebuild */
    public const FAILURE_WINDOW_MINUTES = 10;

    /**
     * Queue a rebuild of an archive whose file is gone. Only commits that had an
     * archive qualify: building any commit asked for would let a registry token
     * read commits that were never published.
     */
    public function handle(Organization $organization, string $packageName, string $version, string $reference): bool
    {
        $archive = DistArchive::query()
            ->where('source_reference', $reference)
            ->whereHas('package', fn (Builder $query) => $query
                ->where('organization_uuid', $organization->uuid)
                ->where('name', $packageName))
            ->whereHas('packageVersion', fn (Builder $query) => $query->matchingVersion($version))
            ->with('package')
            ->first();

        if (! $archive || ! $archive->package->hasRebuildableDists()) {
            return false;
        }

        if ($archive->rebuild_failed_at?->isAfter(now()->subMinutes(self::FAILURE_WINDOW_MINUTES))) {
            return false;
        }

        RebuildDistArchiveJob::dispatch($archive);

        return true;
    }
}
