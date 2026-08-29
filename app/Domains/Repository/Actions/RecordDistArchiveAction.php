<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\DistArchiveData;
use App\Models\DistArchive;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecordDistArchiveAction
{
    /**
     * Record an archive and point the version at it.
     *
     * The single writer of dist_archives rows and of the package_versions
     * dist_* columns, which are a write-through cache of the current archive.
     * Archives the version has moved past are marked detached but their files
     * are deliberately kept, so lock files pinning those commits still install.
     */
    public function handle(
        PackageVersion $version,
        DistArchiveData $archive,
        string $organizationSlug,
    ): ?DistArchive {
        $reference = $version->source_reference;

        if (! $reference) {
            return null;
        }

        return DB::transaction(function () use ($version, $archive, $organizationSlug, $reference): DistArchive {
            $existingPath = DistArchive::query()
                ->where('package_version_uuid', $version->uuid)
                ->where('source_reference', $reference)
                ->value('path');

            $distArchive = DistArchive::query()->updateOrCreate(
                [
                    'package_version_uuid' => $version->uuid,
                    'source_reference' => $reference,
                ],
                [
                    'package_uuid' => $version->package_uuid,
                    'path' => $archive->path,
                    'shasum' => $archive->shasum,
                    'size' => $archive->size,
                    'detached_at' => null,
                ],
            );

            // Only reachable when the organization slug or package name changed
            // under an existing archive; without this the old file is stranded.
            if ($existingPath && $existingPath !== $archive->path) {
                Storage::disk(config('pricore.dist.disk'))->delete($existingPath);
            }

            DistArchive::query()
                ->where('package_version_uuid', $version->uuid)
                ->whereKeyNot($distArchive->uuid)
                ->whereNull('detached_at')
                ->update(['detached_at' => now()]);

            $version->update([
                'dist_url' => DistArchiveData::urlFor(
                    organizationSlug: $organizationSlug,
                    packageName: $version->package->name,
                    version: $version->version,
                    reference: $reference,
                ),
                'dist_path' => $archive->path,
                'dist_shasum' => $archive->shasum,
                'dist_size' => $archive->size,
            ]);

            return $distArchive;
        });
    }
}
