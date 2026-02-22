<?php

namespace App\Domains\Composer\Actions;

use App\Domains\Composer\Contracts\Data\DownloadNotificationData;
use App\Models\Organization;
use App\Models\PackageDownload;

class RecordDownloadsAction
{
    /**
     * Record download notifications for an organization.
     *
     * @param  array<int, DownloadNotificationData>  $downloads
     * @return int Number of recorded downloads
     */
    public function handle(Organization $organization, array $downloads): int
    {
        $packageNames = collect($downloads)->pluck('name')->unique()->all();

        $packageUuids = $organization->packages()
            ->whereIn('name', $packageNames)
            ->pluck('uuid', 'name');

        $now = now();

        $records = collect($downloads)->map(fn (DownloadNotificationData $download) => [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_uuid' => $organization->uuid,
            'package_uuid' => $packageUuids->get($download->name),
            'package_name' => $download->name,
            'version' => $download->version,
            'downloaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        PackageDownload::insert($records);

        return count($records);
    }
}
