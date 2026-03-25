import HeadingSmall from '@/components/heading-small';
import { StatCard } from '@/components/stats/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardList } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronRight,
    Shield,
    ShieldAlert,
    ShieldX,
} from 'lucide-react';
import { DateTime } from 'luxon';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type PackageSecuritySummaryData =
    App.Domains.Security.Contracts.Data.PackageSecuritySummaryData;

interface SecurityStats {
    affectedPackages: number;
    totalVulnerabilities: number;
    criticalCount: number;
    highCount: number;
    mediumCount: number;
    lowCount: number;
}

interface SecurityIndexProps {
    organization: OrganizationData;
    stats: SecurityStats;
    packages: PackageSecuritySummaryData[];
    filters: {
        severity: string;
    };
    lastSyncedAt: string | null;
}

function SeverityBadge({
    severity,
    count,
}: {
    severity: string;
    count: number;
}) {
    if (count === 0) return null;

    const variants: Record<
        string,
        { className: string; label: string }
    > = {
        critical: {
            className:
                'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400',
            label: 'Critical',
        },
        high: {
            className:
                'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-400',
            label: 'High',
        },
        medium: {
            className:
                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400',
            label: 'Medium',
        },
        low: {
            className:
                'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400',
            label: 'Low',
        },
    };

    const variant = variants[severity];
    if (!variant) return null;

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs ${variant.className}`}
        >
            {count} {variant.label}
        </span>
    );
}

export default function SecurityIndex({
    organization,
    stats,
    packages,
    filters,
    lastSyncedAt,
}: SecurityIndexProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Security',
            href: `/organizations/${organization.slug}/security`,
        },
    ];

    const handleSeverityChange = (value: string) => {
        const params: Record<string, string> = {};
        if (value && value !== 'all') {
            params.severity = value;
        }
        router.get(
            `/organizations/${organization.slug}/security`,
            params,
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Security - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-medium">Security</h1>
                        {lastSyncedAt && (
                            <p className="text-sm text-muted-foreground">
                                Advisories last synced{' '}
                                {DateTime.fromISO(
                                    lastSyncedAt,
                                ).toRelative()}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        title="Vulnerabilities"
                        value={stats.totalVulnerabilities}
                        icon={ShieldAlert}
                        variant={
                            stats.totalVulnerabilities > 0
                                ? 'danger'
                                : 'default'
                        }
                    />
                    <StatCard
                        title="Critical"
                        value={stats.criticalCount}
                        icon={ShieldX}
                        variant={
                            stats.criticalCount > 0 ? 'danger' : 'default'
                        }
                    />
                    <StatCard
                        title="High"
                        value={stats.highCount}
                        icon={AlertTriangle}
                        variant={
                            stats.highCount > 0 ? 'warning' : 'default'
                        }
                    />
                    <StatCard
                        title="Medium"
                        value={stats.mediumCount}
                        icon={AlertTriangle}
                    />
                    <StatCard
                        title="Low"
                        value={stats.lowCount}
                        icon={Shield}
                    />
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title="Affected Packages"
                            description={`${stats.affectedPackages} package${stats.affectedPackages === 1 ? '' : 's'} with vulnerabilities in latest or dev version`}
                        />
                        <Select
                            value={filters.severity || 'all'}
                            onValueChange={handleSeverityChange}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All severities" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All severities
                                </SelectItem>
                                <SelectItem value="critical">
                                    Critical
                                </SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="medium">
                                    Medium
                                </SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {packages.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Shield className="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
                                <p className="font-medium">
                                    No vulnerabilities found
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {filters.severity
                                        ? 'No packages match the selected severity filter.'
                                        : 'All packages are clear of known security advisories.'}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <CardList>
                            {packages.map((pkg, index) => (
                                <Link
                                    key={pkg.packageUuid}
                                    href={`/organizations/${organization.slug}/packages/${pkg.packageUuid}`}
                                    className={`flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/50 ${index < packages.length - 1 ? 'border-b' : ''}`}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate font-medium">
                                                {pkg.packageName}
                                            </span>
                                            <Badge variant="destructive">
                                                {pkg.totalCount} vulnerabilit
                                                {pkg.totalCount === 1
                                                    ? 'y'
                                                    : 'ies'}
                                            </Badge>
                                            <span className="text-sm text-muted-foreground">
                                                {pkg.latestVersion}
                                            </span>
                                        </div>
                                        <div className="mt-1.5 flex items-center gap-2">
                                            <SeverityBadge
                                                severity="critical"
                                                count={pkg.criticalCount}
                                            />
                                            <SeverityBadge
                                                severity="high"
                                                count={pkg.highCount}
                                            />
                                            <SeverityBadge
                                                severity="medium"
                                                count={pkg.mediumCount}
                                            />
                                            <SeverityBadge
                                                severity="low"
                                                count={pkg.lowCount}
                                            />
                                        </div>
                                    </div>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                </Link>
                            ))}
                        </CardList>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
