<?php

namespace App\Domains\Security\Http\Controllers;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Security\Contracts\Data\PackageSecuritySummaryData;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Http\Controllers\Controller;
use App\Models\AdvisorySyncMetadata;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SecurityOverviewController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $severityFilter = $request->query('severity', '');

        // Latest stable version + all dev versions per package
        $relevantVersionUuids = $this->getRelevantVersionUuids($organization);

        // Aggregate stats — only for latest versions
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

        // Per-package summaries — only for latest versions
        $packageSummaries = DB::table('security_advisory_matches')
            ->join('package_versions', 'security_advisory_matches.package_version_uuid', '=', 'package_versions.uuid')
            ->join('packages', 'package_versions.package_uuid', '=', 'packages.uuid')
            ->join('security_advisories', 'security_advisory_matches.security_advisory_uuid', '=', 'security_advisories.uuid')
            ->where('packages.organization_uuid', $organization->uuid)
            ->whereIn('package_versions.uuid', $relevantVersionUuids)
            ->when($severityFilter && AdvisorySeverity::tryFrom($severityFilter), function ($q) use ($severityFilter) {
                $q->where('security_advisories.severity', $severityFilter);
            })
            ->select([
                'packages.uuid as package_uuid',
                'packages.name as package_name',
                'package_versions.version as latest_version',
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'critical' THEN 1 ELSE 0 END) as critical_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'high' THEN 1 ELSE 0 END) as high_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'medium' THEN 1 ELSE 0 END) as medium_count"),
                DB::raw("SUM(CASE WHEN security_advisories.severity = 'low' THEN 1 ELSE 0 END) as low_count"),
            ])
            ->groupBy('packages.uuid', 'packages.name', 'package_versions.version')
            ->orderByRaw("SUM(CASE WHEN security_advisories.severity = 'critical' THEN 1 ELSE 0 END) DESC")
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(fn (object $row) => new PackageSecuritySummaryData(
                packageUuid: $row->package_uuid,
                packageName: $row->package_name,
                latestVersion: $row->latest_version,
                criticalCount: (int) $row->critical_count,
                highCount: (int) $row->high_count,
                mediumCount: (int) $row->medium_count,
                lowCount: (int) $row->low_count,
                totalCount: (int) $row->total_count,
            ));

        $syncMetadata = AdvisorySyncMetadata::first();

        return Inertia::render('organizations/security/index', [
            'organization' => OrganizationData::fromModel($organization),
            'stats' => [
                'affectedPackages' => (int) ($stats->affected_packages ?? 0),
                'totalVulnerabilities' => (int) ($stats->total_vulnerabilities ?? 0),
                'criticalCount' => (int) ($stats->critical_count ?? 0),
                'highCount' => (int) ($stats->high_count ?? 0),
                'mediumCount' => (int) ($stats->medium_count ?? 0),
                'lowCount' => (int) ($stats->low_count ?? 0),
            ],
            'packages' => $packageSummaries,
            'filters' => [
                'severity' => $severityFilter,
            ],
            'lastSyncedAt' => $syncMetadata?->last_synced_at?->toISOString(),
        ]);
    }

    /**
     * Get the relevant version UUIDs for each package: latest stable + all dev versions.
     * Latest stable = what's in production. Dev versions = active branches.
     *
     * @return array<int, string>
     */
    protected function getRelevantVersionUuids(Organization $organization): array
    {
        $packages = Package::where('organization_uuid', $organization->uuid)->get();

        return $packages->flatMap(function (Package $package) {
            // Latest stable version
            $latestStable = PackageVersion::where('package_uuid', $package->uuid)
                ->stable()
                ->orderBySemanticVersion('desc')
                ->value('uuid');

            // All dev versions (active branches)
            $devVersions = PackageVersion::where('package_uuid', $package->uuid)
                ->dev()
                ->pluck('uuid')
                ->all();

            return array_filter(array_merge([$latestStable], $devVersions));
        })->values()->all();
    }
}
