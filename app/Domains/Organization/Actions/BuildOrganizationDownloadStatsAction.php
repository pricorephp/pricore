<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\DailyDownloadData;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildOrganizationDownloadStatsAction
{
    /**
     * @return array{totalDownloads: int, dailyDownloads: array<int, DailyDownloadData>}
     */
    public function handle(Organization $organization): array
    {
        $startDate = Carbon::now()->subDays(29)->startOfDay();

        $dailyCounts = $organization->downloads()
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

        $totalDownloads = $organization->downloads()->count();

        return [
            'totalDownloads' => $totalDownloads,
            'dailyDownloads' => $dailyDownloads,
        ];
    }
}
