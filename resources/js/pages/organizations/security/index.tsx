import ScanSecurityController from '@/actions/App/Domains/Security/Http/Controllers/ScanSecurityController';
import HeadingSmall from '@/components/heading-small';
import { StatCard } from '@/components/stats/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    ChevronRight,
    LoaderCircle,
    RefreshCw,
    Shield,
    ShieldAlert,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useRef, useState } from 'react';

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

function SeverityDot({
    color,
    label,
    count,
}: {
    color: string;
    label: string;
    count: number;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-1 px-4">
            <div className="flex items-center gap-2">
                <span className={`size-2 shrink-0 rounded-full ${color}`} />
                <p className="text-2xl font-semibold tabular-nums">{count}</p>
            </div>
            <p className="text-sm text-muted-foreground">{label}</p>
        </div>
    );
}

function SeverityBadge({
    severity,
    count,
}: {
    severity: string;
    count: number;
}) {
    if (count === 0) return null;

    const variants: Record<string, { className: string; label: string }> = {
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

    const [scanning, setScanning] = useState(false);
    const scanningRef = useRef(false);

    useEffect(() => {
        if (!window.Echo) return;

        const channel = window.Echo.private(
            `organization.${organization.uuid}`,
        );

        channel.listen('.security.advisories-updated', () => {
            router.reload();
            if (scanningRef.current) {
                setScanning(false);
                scanningRef.current = false;
            }
        });

        return () => {
            window.Echo.leave(`organization.${organization.uuid}`);
        };
    }, [organization.uuid]);

    const handleScan = () => {
        setScanning(true);
        scanningRef.current = true;
        router.post(ScanSecurityController.url(organization.slug));
    };

    const handleSeverityChange = (value: string) => {
        const params: Record<string, string> = {};
        if (value && value !== 'all') {
            params.severity = value;
        }
        router.get(`/organizations/${organization.slug}/security`, params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Security - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-medium">Security</h1>
                        <p className="text-muted-foreground">
                            {lastSyncedAt
                                ? `Advisories last synced ${DateTime.fromISO(lastSyncedAt).toRelative()}`
                                : 'Advisories have not been synced yet'}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleScan}
                        disabled={scanning}
                    >
                        {scanning ? (
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                        ) : (
                            <RefreshCw className="h-4 w-4" />
                        )}
                        Scan Now
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
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
                    <Card>
                        <CardContent className="grid h-full grid-cols-4 divide-x py-5">
                            <SeverityDot
                                color="bg-red-500"
                                label="Critical"
                                count={stats.criticalCount}
                            />
                            <SeverityDot
                                color="bg-orange-500"
                                label="High"
                                count={stats.highCount}
                            />
                            <SeverityDot
                                color="bg-amber-500"
                                label="Medium"
                                count={stats.mediumCount}
                            />
                            <SeverityDot
                                color="bg-blue-500"
                                label="Low"
                                count={stats.lowCount}
                            />
                        </CardContent>
                    </Card>
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
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {packages.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Shield className="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
                                <p className="text-lg font-medium">
                                    No vulnerabilities found
                                </p>
                                <p className="mt-1 text-muted-foreground">
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
                                                {pkg.affectedVersionCount}{' '}
                                                {pkg.affectedVersionCount === 1
                                                    ? 'version'
                                                    : 'versions'}{' '}
                                                affected
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
