<?php

namespace App\Domains\Mirror\Actions;

use App\Domains\Mirror\Contracts\Enums\SyncVersionResult;
use App\Domains\Repository\Actions\DetachDistArchivesTask;
use App\Models\Package;
use App\Models\PackageVersion;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMirrorPackageVersionAction
{
    public function __construct(
        protected DetachDistArchivesTask $detachDistArchivesTask,
    ) {}

    /**
     * @param  array<string, mixed>  $composerJson
     */
    public function handle(
        Package $package,
        string $version,
        array $composerJson,
    ): SyncVersionResult {
        $normalizedVersion = $this->normalizeVersion($version);
        $sourceReference = $this->extractReference($composerJson, $version);

        $existingVersion = PackageVersion::query()
            ->where('package_uuid', $package->uuid)
            ->where('version', $version)
            ->first();

        if ($existingVersion) {
            if ($existingVersion->source_reference === $sourceReference) {
                return SyncVersionResult::Skipped;
            }

            DB::transaction(function () use ($existingVersion, $normalizedVersion, $composerJson, $sourceReference) {
                // Release the archive built for the old reference before moving,
                // so the stale dist is never advertised under the new one.
                $this->detachDistArchivesTask->handle($existingVersion);

                $existingVersion->update([
                    'normalized_version' => $normalizedVersion,
                    'composer_json' => $composerJson,
                    'source_url' => $composerJson['source']['url'] ?? null,
                    'source_reference' => $sourceReference,
                    'released_at' => isset($composerJson['time']) ? $composerJson['time'] : null,
                ]);
            });

            return SyncVersionResult::Updated;
        }

        PackageVersion::create([
            'package_uuid' => $package->uuid,
            'version' => $version,
            'normalized_version' => $normalizedVersion,
            'composer_json' => $composerJson,
            'source_url' => $composerJson['source']['url'] ?? null,
            'source_reference' => $sourceReference,
            'released_at' => isset($composerJson['time']) ? $composerJson['time'] : now(),
        ]);

        return SyncVersionResult::Added;
    }

    protected function normalizeVersion(string $version): string
    {
        try {
            return (new VersionParser)->normalize($version);
        } catch (\Throwable $e) {
            Log::warning('Failed to normalize version', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return $version;
        }
    }

    /**
     * Extract a reference identifier for this version.
     *
     * @param  array<string, mixed>  $composerJson
     */
    protected function extractReference(array $composerJson, string $version): string
    {
        if (isset($composerJson['dist']['reference'])) {
            return (string) $composerJson['dist']['reference'];
        }

        if (isset($composerJson['source']['reference'])) {
            return (string) $composerJson['source']['reference'];
        }

        return hash('sha256', $version.json_encode($composerJson));
    }
}
