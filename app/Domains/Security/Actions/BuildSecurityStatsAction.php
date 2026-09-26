<?php

namespace App\Domains\Security\Actions;

use App\Domains\Security\Contracts\Data\SecurityStatsData;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class BuildSecurityStatsAction
{
    public function __construct(
        protected CollectRelevantVersionUuidsAction $collectRelevantVersionUuidsAction,
    ) {}

    /**
     * @param  array<int, string>|null  $relevantVersionUuids
     */
    public function handle(Organization $organization, ?array $relevantVersionUuids = null): SecurityStatsData
    {
        $relevantVersionUuids ??= $this->collectRelevantVersionUuidsAction->handle($organization);

        $stats = DB::table('security_advisory_matches')
            ->join('package_versions', 'security_advisory_matches.package_version_uuid', '=', 'package_versions.uuid')
            ->join('packages', 'package_versions.package_uuid', '=', 'packages.uuid')
            ->join('security_advisories', 'security_advisory_matches.security_advisory_uuid', '=', 'security_advisories.uuid')
            ->where('packages.organization_uuid', $organization->uuid)
            ->whereIn('package_versions.uuid', $relevantVersionUuids)
            ->select([
                DB::raw('COUNT(DISTINCT packages.uuid) as affected_packages'),
                DB::raw('COUNT(*) as total_vulnerabilities'),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'critical' THEN 1 ELSE 0 END) as critical_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'high' THEN 1 ELSE 0 END) as high_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'medium' THEN 1 ELSE 0 END) as medium_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'low' THEN 1 ELSE 0 END) as low_count"),
            ])
            ->first();

        return new SecurityStatsData(
            affectedPackages: (int) ($stats->affected_packages ?? 0),
            totalVulnerabilities: (int) ($stats->total_vulnerabilities ?? 0),
            criticalCount: (int) ($stats->critical_count ?? 0),
            highCount: (int) ($stats->high_count ?? 0),
            mediumCount: (int) ($stats->medium_count ?? 0),
            lowCount: (int) ($stats->low_count ?? 0),
        );
    }
}
