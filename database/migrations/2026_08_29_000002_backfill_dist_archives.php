<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Record an archive row for every version that already has one on disk.
     *
     * Idempotent so an interrupted run can simply be repeated. Reads no files:
     * shasum and size come from the columns, both of which are legitimately null
     * for older rows.
     */
    public function up(): void
    {
        DB::table('package_versions')
            ->whereNotNull('dist_path')
            ->whereNotNull('source_reference')
            ->chunkById(500, function ($packageVersions) {
                $alreadyRecorded = DB::table('dist_archives')
                    ->whereIn('package_version_uuid', collect($packageVersions)->pluck('uuid'))
                    ->pluck('package_version_uuid')
                    ->all();

                $rows = collect($packageVersions)
                    ->reject(fn ($packageVersion) => in_array($packageVersion->uuid, $alreadyRecorded, true))
                    ->map(fn ($packageVersion) => [
                        'uuid' => (string) Str::uuid(),
                        'package_uuid' => $packageVersion->package_uuid,
                        'package_version_uuid' => $packageVersion->uuid,
                        'source_reference' => $packageVersion->source_reference,
                        'path' => $packageVersion->dist_path,
                        'shasum' => $packageVersion->dist_shasum,
                        'size' => $packageVersion->dist_size,
                        // Every backfilled archive is the current one for its
                        // version, so nothing becomes prunable on upgrade.
                        'detached_at' => null,
                        'created_at' => $packageVersion->created_at,
                        'updated_at' => $packageVersion->updated_at,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('dist_archives')->insert($rows);
                }
            }, 'uuid');
    }

    public function down(): void
    {
        // The table itself is dropped by the migration that created it.
    }
};
