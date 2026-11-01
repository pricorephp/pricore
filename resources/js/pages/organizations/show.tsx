import GitProviderIcon, {
    getProviderColor,
} from '@/components/git-provider-icon';
import OnboardingChecklist from '@/components/onboarding-checklist';
import { RelativeTime } from '@/components/relative-time';
import { ActivityTimeline } from '@/components/stats/activity-timeline';
import { DownloadChart } from '@/components/stats/download-chart';
import { FrequentPackages } from '@/components/stats/frequent-packages';
import {
    StatusDot,
    statusTone,
    SyncHealthStrip,
} from '@/components/sync-status';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganizationChannel } from '@/hooks/use-organization-channel';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn } from '@/lib/utils';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

type ActivityLogData = App.Domains.Activity.Contracts.Data.ActivityLogData;
type FrequentPackageData =
    App.Domains.Package.Contracts.Data.FrequentPackageData;
type OnboardingChecklistData =
    App.Domains.Organization.Contracts.Data.OnboardingChecklistData;

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationStatsData =
    App.Domains.Organization.Contracts.Data.OrganizationStatsData;
type RepositoryHealthData =
    App.Domains.Repository.Contracts.Data.RepositoryHealthData;
type SecurityStatsData = App.Domains.Security.Contracts.Data.SecurityStatsData;

interface OrganizationShowProps {
    organization: OrganizationData;
    stats: OrganizationStatsData;
    onboarding: OnboardingChecklistData;
    configuredProviders?: string[];
    repositories: RepositoryHealthData[];
    securityStats?: SecurityStatsData;
    activityLogs?: ActivityLogData[];
    frequentPackages?: FrequentPackageData[];
}

export default function OrganizationShow({
    organization,
    stats,
    onboarding,
    configuredProviders = [],
    repositories,
    securityStats,
    activityLogs,
    frequentPackages,
}: OrganizationShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    useOrganizationChannel(organization.uuid);

    const [cachedActivityLogs, setCachedActivityLogs] = useState(activityLogs);
    if (activityLogs !== undefined && activityLogs !== cachedActivityLogs) {
        setCachedActivityLogs(activityLogs);
    }

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />

            <div className="mx-auto w-7xl space-y-8 p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {organization.name}
                        </h1>
                        <p className="flex flex-wrap items-center gap-x-1.5 text-muted-foreground">
                            <SummaryLink
                                href={`/organizations/${organization.slug}/packages`}
                                count={stats.packagesCount}
                                noun="package"
                            />
                            <span>&middot;</span>
                            <SummaryLink
                                href={`/organizations/${organization.slug}/repositories`}
                                count={stats.repositoriesCount}
                                noun="repository"
                                plural="repositories"
                            />
                            <span>&middot;</span>
                            <SummaryLink
                                href={`/organizations/${organization.slug}/settings/members`}
                                count={stats.membersCount}
                                noun="member"
                            />
                        </p>
                    </div>
                    {repositories.length > 0 && (
                        <HealthSummary repositories={repositories} />
                    )}
                </header>

                <OnboardingChecklist
                    organization={organization}
                    onboarding={onboarding}
                    configuredProviders={configuredProviders}
                    composerRepositoryUrl={organization.composerRepositoryUrl}
                />

                {repositories.length > 0 && (
                    <section className="space-y-3">
                        <SectionHeader
                            title="Repositories"
                            href={`/organizations/${organization.slug}/repositories`}
                            linkLabel="All repositories"
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {repositories
                                .slice(0, REPOSITORY_TILE_LIMIT)
                                .map((repository) => (
                                    <RepositoryTile
                                        key={repository.uuid}
                                        organizationSlug={organization.slug}
                                        repository={repository}
                                    />
                                ))}
                        </div>
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Deferred
                        data="securityStats"
                        fallback={<SecurityPanelSkeleton />}
                    >
                        <SecurityPanel
                            organizationSlug={organization.slug}
                            stats={securityStats}
                        />
                    </Deferred>
                    <DownloadChart
                        title="Downloads (Last 30 Days)"
                        data={stats.dailyDownloads}
                        className="lg:col-span-2"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {/* Activity Timeline */}
                        <Deferred
                            data="activityLogs"
                            fallback={
                                <ActivityTimeline
                                    organizationSlug={organization.slug}
                                    activities={cachedActivityLogs}
                                />
                            }
                        >
                            <ActivityTimeline
                                organizationSlug={organization.slug}
                                activities={activityLogs}
                            />
                        </Deferred>
                    </div>

                    <div>
                        {/* Frequently Viewed Packages */}
                        <Deferred
                            data="frequentPackages"
                            fallback={
                                <FrequentPackages
                                    organizationSlug={organization.slug}
                                    packages={undefined}
                                />
                            }
                        >
                            <FrequentPackages
                                organizationSlug={organization.slug}
                                packages={frequentPackages}
                            />
                        </Deferred>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

const REPOSITORY_TILE_LIMIT = 6;

function SummaryLink({
    href,
    count,
    noun,
    plural,
}: {
    href: string;
    count: number;
    noun: string;
    plural?: string;
}) {
    return (
        <Link href={href} className="transition-colors hover:text-foreground">
            <span className="font-medium text-foreground tabular-nums">
                {count}
            </span>{' '}
            {count === 1 ? noun : (plural ?? `${noun}s`)}
        </Link>
    );
}

function SectionHeader({
    title,
    href,
    linkLabel,
    children,
}: {
    title: string;
    href?: string;
    linkLabel?: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
            {children}
            {href && linkLabel && (
                <Link
                    href={href}
                    className="group inline-flex items-center gap-1 text-muted-foreground transition-colors hover:text-foreground"
                >
                    {linkLabel}
                    <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                </Link>
            )}
        </div>
    );
}

function HealthSummary({
    repositories,
}: {
    repositories: RepositoryHealthData[];
}) {
    const counts = {
        success: repositories.filter((r) => r.syncStatus === 'ok').length,
        danger: repositories.filter((r) => r.syncStatus === 'failed').length,
        pending: repositories.filter(
            (r) => r.syncStatus === 'pending' || r.syncStatus === null,
        ).length,
    };

    const items = [
        { tone: 'success' as const, count: counts.success, label: 'healthy' },
        { tone: 'danger' as const, count: counts.danger, label: 'failing' },
        { tone: 'pending' as const, count: counts.pending, label: 'pending' },
    ].filter((item) => item.count > 0);

    return (
        <div className="flex items-center gap-4 rounded-lg border bg-card px-4 py-2">
            {items.map((item) => (
                <span
                    key={item.label}
                    className="inline-flex items-center gap-2"
                >
                    <StatusDot tone={item.tone} />
                    <span className="font-medium tabular-nums">
                        {item.count}
                    </span>
                    <span className="text-muted-foreground">{item.label}</span>
                </span>
            ))}
        </div>
    );
}

function RepositoryTile({
    organizationSlug,
    repository,
}: {
    organizationSlug: string;
    repository: RepositoryHealthData;
}) {
    return (
        <Link
            href={`/organizations/${organizationSlug}/repositories/${repository.uuid}`}
            className="group flex flex-col gap-4 rounded-lg border bg-card p-4 transition-colors hover:border-foreground/20"
        >
            <div className="flex items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-surface-inset">
                    <GitProviderIcon
                        provider={repository.provider}
                        className={cn(
                            'size-5',
                            getProviderColor(repository.provider),
                        )}
                    />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="truncate font-medium transition-colors group-hover:text-primary">
                        {repository.name}
                    </div>
                    <div className="truncate font-mono text-xs text-muted-foreground">
                        {repository.repoIdentifier}
                    </div>
                </div>
            </div>
            <div className="flex items-end justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <StatusDot tone={statusTone(repository.syncStatus)} />
                        {repository.syncStatusLabel ?? 'Pending'}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {repository.lastSyncedAt ? (
                            <>
                                Synced{' '}
                                <RelativeTime
                                    datetime={repository.lastSyncedAt}
                                />
                            </>
                        ) : (
                            'Never synced'
                        )}
                        {' · '}
                        {repository.packagesCount}{' '}
                        {repository.packagesCount === 1
                            ? 'package'
                            : 'packages'}
                    </div>
                </div>
                <SyncHealthStrip
                    syncs={repository.recentSyncs}
                    showLabel={false}
                />
            </div>
        </Link>
    );
}

const SEVERITIES = [
    { key: 'criticalCount', label: 'Critical', color: 'bg-red-500' },
    { key: 'highCount', label: 'High', color: 'bg-orange-500' },
    { key: 'mediumCount', label: 'Medium', color: 'bg-amber-500' },
    { key: 'lowCount', label: 'Low', color: 'bg-blue-500' },
] as const;

function SecurityPanel({
    organizationSlug,
    stats,
}: {
    organizationSlug: string;
    stats?: SecurityStatsData;
}) {
    if (!stats) {
        return <SecurityPanelSkeleton />;
    }

    const total = stats.totalVulnerabilities;

    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Security</CardTitle>
                <Link
                    href={`/organizations/${organizationSlug}/security`}
                    className="group inline-flex items-center gap-1 text-muted-foreground transition-colors hover:text-foreground"
                >
                    Details
                    <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                </Link>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col">
                {total === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 py-6 text-center">
                        <ShieldCheck className="size-8 text-emerald-500" />
                        <div className="font-medium">
                            No known vulnerabilities
                        </div>
                        <p className="text-muted-foreground">
                            Latest versions are clear of published advisories.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-5">
                        <div>
                            <div className="text-3xl font-semibold tracking-tight tabular-nums">
                                {total}
                            </div>
                            <div className="text-muted-foreground">
                                {total === 1
                                    ? 'vulnerability'
                                    : 'vulnerabilities'}{' '}
                                in {stats.affectedPackages}{' '}
                                {stats.affectedPackages === 1
                                    ? 'package'
                                    : 'packages'}
                            </div>
                        </div>
                        <div className="flex h-2 gap-0.5 overflow-hidden rounded-full">
                            {SEVERITIES.filter(
                                (severity) => stats[severity.key] > 0,
                            ).map((severity) => (
                                <span
                                    key={severity.key}
                                    className={severity.color}
                                    style={{ flexGrow: stats[severity.key] }}
                                />
                            ))}
                        </div>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
                            {SEVERITIES.map((severity) => (
                                <div
                                    key={severity.key}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <dt className="inline-flex items-center gap-2 text-muted-foreground">
                                        <span
                                            className={cn(
                                                'size-2 rounded-full',
                                                severity.color,
                                                stats[severity.key] === 0 &&
                                                    'opacity-30',
                                            )}
                                        />
                                        {severity.label}
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {stats[severity.key]}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SecurityPanelSkeleton() {
    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle>Security</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <Skeleton className="h-8 w-16" />
                <Skeleton className="h-2 w-full" />
                <Skeleton className="h-12 w-full" />
            </CardContent>
        </Card>
    );
}
