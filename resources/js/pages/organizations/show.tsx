import OnboardingChecklist from '@/components/onboarding-checklist';
import { ActivityTimeline } from '@/components/stats/activity-timeline';
import { DownloadChart } from '@/components/stats/download-chart';
import { FrequentPackages } from '@/components/stats/frequent-packages';
import { StatCard } from '@/components/stats/stat-card';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Box, Download, GitBranch, Users } from 'lucide-react';

type ActivityLogData = App.Domains.Activity.Contracts.Data.ActivityLogData;
type FrequentPackageData =
    App.Domains.Package.Contracts.Data.FrequentPackageData;
type OnboardingChecklistData =
    App.Domains.Organization.Contracts.Data.OnboardingChecklistData;

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationStatsData =
    App.Domains.Organization.Contracts.Data.OrganizationStatsData;

interface OrganizationShowProps {
    organization: OrganizationData;
    stats: OrganizationStatsData;
    onboarding: OnboardingChecklistData;
    configuredProviders?: string[];
    activityLogs?: ActivityLogData[];
    frequentPackages?: FrequentPackageData[];
}

export default function OrganizationShow({
    organization,
    stats,
    onboarding,
    configuredProviders = [],
    activityLogs,
    frequentPackages,
}: OrganizationShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-medium">{organization.name}</h1>
                </div>

                {/* Quick Stats Row */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link href={`/organizations/${organization.slug}/packages`}>
                        <StatCard
                            title="Packages"
                            value={stats.packagesCount}
                            icon={Box}
                            clickable
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/repositories`}
                    >
                        <StatCard
                            title="Repositories"
                            value={stats.repositoriesCount}
                            icon={GitBranch}
                            clickable
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/settings/members`}
                    >
                        <StatCard
                            title="Members"
                            value={stats.membersCount}
                            icon={Users}
                            clickable
                        />
                    </Link>

                    <StatCard
                        title="Total Downloads"
                        value={stats.totalDownloads.toLocaleString()}
                        icon={Download}
                    />
                </div>

                {/* Download Chart */}
                <DownloadChart
                    title="Downloads (Last 30 Days)"
                    data={stats.dailyDownloads}
                />

                {/* Onboarding Checklist */}
                <OnboardingChecklist
                    organization={organization}
                    onboarding={onboarding}
                    configuredProviders={configuredProviders}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {/* Activity Timeline */}
                        <Deferred
                            data="activityLogs"
                            fallback={
                                <ActivityTimeline
                                    organizationSlug={organization.slug}
                                    activities={undefined}
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
