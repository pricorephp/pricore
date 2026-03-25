<?php

namespace App\Domains\Security\Actions;

use App\Domains\Security\Contracts\Data\AdvisorySyncResultData;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Domains\Security\Services\PackagistAdvisoryClient;
use App\Models\AdvisorySyncMetadata;
use App\Models\SecurityAdvisory;
use Illuminate\Support\Facades\Log;

class FetchAdvisoriesAction
{
    public function __construct(
        protected PackagistAdvisoryClient $packagistAdvisoryClient,
    ) {}

    public function handle(): AdvisorySyncResultData
    {
        $syncMetadata = AdvisorySyncMetadata::firstOrCreate([], [
            'advisories_count' => 0,
        ]);

        $advisories = $syncMetadata->last_updated_since
            ? $this->packagistAdvisoryClient->fetchUpdatedSince($syncMetadata->last_updated_since)
            : $this->packagistAdvisoryClient->fetchAll();

        $added = 0;
        $updated = 0;

        foreach ($advisories as $packageName => $packageAdvisories) {
            foreach ($packageAdvisories as $advisoryData) {
                $wasRecentlyCreated = $this->upsertAdvisory($packageName, $advisoryData);

                if ($wasRecentlyCreated) {
                    $added++;
                } else {
                    $updated++;
                }
            }
        }

        $syncMetadata->update([
            'last_synced_at' => now(),
            'last_updated_since' => now()->timestamp,
            'advisories_count' => SecurityAdvisory::count(),
        ]);

        Log::info('Advisory sync completed', [
            'advisories_added' => $added,
            'advisories_updated' => $updated,
        ]);

        return new AdvisorySyncResultData(
            advisoriesAdded: $added,
            advisoriesUpdated: $updated,
        );
    }

    /**
     * @param  array<string, mixed>  $advisoryData
     * @return bool Whether the advisory was newly created
     */
    protected function upsertAdvisory(string $packageName, array $advisoryData): bool
    {
        $advisoryId = $advisoryData['advisoryId'] ?? '';

        if (empty($advisoryId)) {
            return false;
        }

        $severity = AdvisorySeverity::tryFrom($advisoryData['severity'] ?? '') ?? AdvisorySeverity::Unknown;

        $advisory = SecurityAdvisory::updateOrCreate(
            ['advisory_id' => $advisoryId],
            [
                'package_name' => $packageName,
                'title' => $advisoryData['title'] ?? '',
                'link' => $advisoryData['link'] ?? null,
                'cve' => $advisoryData['cve'] ?? null,
                'affected_versions' => $advisoryData['affectedVersions'] ?? '',
                'severity' => $severity,
                'sources' => $advisoryData['sources'] ?? null,
                'reported_at' => isset($advisoryData['reportedAt']) ? $advisoryData['reportedAt'] : null,
                'composer_repository' => $advisoryData['composerRepository'] ?? null,
            ],
        );

        return $advisory->wasRecentlyCreated;
    }
}
