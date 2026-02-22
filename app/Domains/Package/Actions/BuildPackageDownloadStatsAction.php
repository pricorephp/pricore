<?php

namespace App\Domains\Package\Actions;

use App\Domains\Organization\Contracts\Data\DailyDownloadData;
use App\Domains\Package\Contracts\Data\PackageDownloadStatsData;
use App\Domains\Package\Contracts\Data\VersionDownloadData;
use App\Models\Package;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildPackageDownloadStatsAction
{
    public function handle(Package $package): PackageDownloadStatsData
    {
        $startDate = Carbon::now()->subDays(29)->startOfDay();

        $dailyCounts = $package->downloads()
            ->where('downloaded_at', '>=', $startDate)
            ->select(DB::raw('DATE(downloaded_at) as date'), DB::raw('COUNT(*) as downloads'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('downloads', 'date');

        $dailyDownloads = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyDownloads[] = new DailyDownloadData(
                date: $date,
                downloads: (int) ($dailyCounts[$date] ?? 0),
            );
        }

        $versionBreakdown = $package->downloads()
            ->select('version', DB::raw('COUNT(*) as downloads'))
            ->groupBy('version')
            ->orderByDesc('downloads')
            ->limit(10)
            ->get()
            ->map(fn ($row) => new VersionDownloadData(
                version: $row->version,
                downloads: (int) $row->getAttribute('downloads'),
            ))
            ->all();

        return new PackageDownloadStatsData(
            totalDownloads: $package->downloads()->count(),
            dailyDownloads: $dailyDownloads,
            versionBreakdown: $versionBreakdown,
        );
    }
}
