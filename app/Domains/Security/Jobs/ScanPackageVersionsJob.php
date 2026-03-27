<?php

namespace App\Domains\Security\Jobs;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Security\Actions\MatchAdvisoriesForPackageAction;
use App\Domains\Security\Notifications\NewVulnerabilitiesNotification;
use App\Models\Package;
use App\Models\SecurityAdvisoryMatch;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScanPackageVersionsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public Package $package,
    ) {}

    public function handle(
        MatchAdvisoriesForPackageAction $matchAdvisoriesForPackageAction,
        RecordActivityTask $recordActivityTask,
    ): void {
        if (! $this->package->organization->security_audits_enabled) {
            return;
        }

        $matchesCreated = $matchAdvisoriesForPackageAction->handle($this->package);

        if ($matchesCreated === 0) {
            return;
        }

        Log::info('Security scan found new advisory matches', [
            'package' => $this->package->name,
            'matches_created' => $matchesCreated,
        ]);

        $organization = $this->package->organization;

        // Record activity
        $recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::VulnerabilitiesDetected,
            subject: $this->package,
            properties: [
                'name' => $this->package->name,
                'matches_created' => $matchesCreated,
            ],
        );

        // Send notification to org admins
        $this->notifyAdmins();
    }

    protected function notifyAdmins(): void
    {
        $organization = $this->package->organization;

        if (! $organization->security_notifications_enabled) {
            return;
        }

        // Get severity counts for all matches on this package
        $severityCounts = SecurityAdvisoryMatch::query()
            ->join('security_advisories', 'security_advisory_matches.security_advisory_uuid', '=', 'security_advisories.uuid')
            ->join('package_versions', 'security_advisory_matches.package_version_uuid', '=', 'package_versions.uuid')
            ->where('package_versions.package_uuid', $this->package->uuid)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN security_advisories.severity = 'critical' THEN 1 ELSE 0 END) as critical")
            ->selectRaw("SUM(CASE WHEN security_advisories.severity = 'high' THEN 1 ELSE 0 END) as high")
            ->selectRaw("SUM(CASE WHEN security_advisories.severity = 'medium' THEN 1 ELSE 0 END) as medium")
            ->selectRaw("SUM(CASE WHEN security_advisories.severity = 'low' THEN 1 ELSE 0 END) as low")
            ->first();

        $topTitles = SecurityAdvisoryMatch::query()
            ->join('security_advisories', 'security_advisory_matches.security_advisory_uuid', '=', 'security_advisories.uuid')
            ->join('package_versions', 'security_advisory_matches.package_version_uuid', '=', 'package_versions.uuid')
            ->where('package_versions.package_uuid', $this->package->uuid)
            ->select('security_advisories.title')
            ->selectRaw("MIN(CASE security_advisories.severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END) as severity_order")
            ->groupBy('security_advisories.title')
            ->orderBy('severity_order')
            ->limit(3)
            ->pluck('security_advisories.title')
            ->all();

        $notification = new NewVulnerabilitiesNotification(
            organization: $organization,
            totalCount: (int) ($severityCounts->total ?? 0),
            severityCounts: [
                'critical' => (int) ($severityCounts->critical ?? 0),
                'high' => (int) ($severityCounts->high ?? 0),
                'medium' => (int) ($severityCounts->medium ?? 0),
                'low' => (int) ($severityCounts->low ?? 0),
            ],
            topAdvisoryTitles: $topTitles,
        );

        // Notify org admins (owner + admin roles)
        $admins = $organization->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
