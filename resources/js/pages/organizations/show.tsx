import HeadingSmall from '@/components/heading-small';
import InfoBox from '@/components/info-box';
import { ActivityFeed } from '@/components/stats/activity-feed';
import { DistributionBar } from '@/components/stats/distribution-bar';
import { StatCard } from '@/components/stats/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Box,
    CheckCircle,
    GitBranch,
    Key,
    Shield,
    Users,
    XCircle,
} from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationStatsData =
    App.Domains.Organization.Contracts.Data.OrganizationStatsData;

interface OrganizationShowProps {
    organization: OrganizationData;
    stats: OrganizationStatsData;
}

export default function OrganizationShow({
    organization,
    stats,
}: OrganizationShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
    ];

    const repoHealthVariant =
        stats.repositoryHealth.successRate >= 80
            ? 'success'
            : stats.repositoryHealth.successRate >= 50
              ? 'warning'
              : stats.repositoriesCount === 0
                ? 'default'
                : 'danger';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title={organization.name}
                        description="Organization overview and statistics"
                    />
                </div>

                {/* Quick Stats Row */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link href={`/organizations/${organization.slug}/packages`}>
                        <StatCard
                            title="Packages"
                            value={stats.packagesCount}
                            icon={Box}
                            description={`${stats.packageMetrics.privatePackages} private, ${stats.packageMetrics.publicPackages} public`}
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/repositories`}
                    >
                        <StatCard
                            title="Repositories"
                            value={stats.repositoriesCount}
                            icon={GitBranch}
                            description={
                                stats.repositoriesCount > 0
                                    ? `${stats.repositoryHealth.successRate}% sync success rate`
                                    : 'No repositories yet'
                            }
                            variant={repoHealthVariant}
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/settings/tokens`}
                    >
                        <StatCard
                            title="API Tokens"
                            value={stats.tokensCount}
                            icon={Key}
                            description={`${stats.tokenMetrics.activeTokens} active, ${stats.tokenMetrics.expiredTokens} expired`}
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/settings/members`}
                    >
                        <StatCard
                            title="Members"
                            value={stats.memberMetrics.totalMembers}
                            icon={Users}
                            description={`${stats.memberMetrics.adminCount} admins, ${stats.memberMetrics.memberCount} members`}
                        />
                    </Link>
                </div>

                {/* Detailed Stats Section */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Repository Health */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <GitBranch className="h-4 w-4" />
                                Repository Health
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Sync Status Distribution
                                </span>
                            </div>
                            <DistributionBar
                                segments={[
                                    {
                                        value: stats.repositoryHealth.okCount,
                                        label: 'OK',
                                        color: 'bg-green-600 dark:bg-green-500',
                                    },
                                    {
                                        value: stats.repositoryHealth
                                            .pendingCount,
                                        label: 'Pending',
                                        color: 'bg-yellow-600 dark:bg-yellow-500',
                                    },
                                    {
                                        value: stats.repositoryHealth
                                            .failedCount,
                                        label: 'Failed',
                                        color: 'bg-red-600 dark:bg-red-500',
                                    },
                                ]}
                            />
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="flex items-center justify-center gap-1 text-green-600 dark:text-green-400">
                                        <CheckCircle className="h-4 w-4" />
                                        <span className="text-lg font-semibold">
                                            {stats.repositoryHealth.okCount}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        OK
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-yellow-600 dark:text-yellow-400">
                                        {stats.repositoryHealth.pendingCount}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Pending
                                    </span>
                                </div>
                                <div>
                                    <div className="flex items-center justify-center gap-1 text-red-600 dark:text-red-400">
                                        <XCircle className="h-4 w-4" />
                                        <span className="text-lg font-semibold">
                                            {stats.repositoryHealth.failedCount}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Failed
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Package Metrics */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Box className="h-4 w-4" />
                                Package Versions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="text-3xl font-bold">
                                {stats.packageMetrics.totalVersions}
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    total versions
                                </span>
                            </div>
                            <DistributionBar
                                segments={[
                                    {
                                        value: stats.packageMetrics
                                            .stableVersions,
                                        label: 'Stable',
                                        color: 'bg-green-600 dark:bg-green-500',
                                    },
                                    {
                                        value: stats.packageMetrics.devVersions,
                                        label: 'Dev',
                                        color: 'bg-blue-600 dark:bg-blue-500',
                                    },
                                ]}
                            />
                            <div className="grid grid-cols-2 gap-4 text-center">
                                <div>
                                    <div className="text-lg font-semibold text-green-600 dark:text-green-400">
                                        {stats.packageMetrics.stableVersions}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Stable Releases
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                        {stats.packageMetrics.devVersions}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Dev Branches
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Token Usage */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Key className="h-4 w-4" />
                                Token Usage
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <DistributionBar
                                segments={[
                                    {
                                        value: stats.tokenMetrics.activeTokens,
                                        label: 'Active (used in 30 days)',
                                        color: 'bg-green-600 dark:bg-green-500',
                                    },
                                    {
                                        value: stats.tokenMetrics.unusedTokens,
                                        label: 'Unused',
                                        color: 'bg-gray-400 dark:bg-gray-500',
                                    },
                                    {
                                        value: stats.tokenMetrics.expiredTokens,
                                        label: 'Expired',
                                        color: 'bg-red-600 dark:bg-red-500',
                                    },
                                ]}
                            />
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="text-lg font-semibold text-green-600 dark:text-green-400">
                                        {stats.tokenMetrics.activeTokens}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Active
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-gray-600 dark:text-gray-400">
                                        {stats.tokenMetrics.unusedTokens}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Unused
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-red-600 dark:text-red-400">
                                        {stats.tokenMetrics.expiredTokens}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Expired
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Member Distribution */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Users className="h-4 w-4" />
                                Team Roles
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <DistributionBar
                                segments={[
                                    {
                                        value: stats.memberMetrics.ownerCount,
                                        label: 'Owners',
                                        color: 'bg-purple-600 dark:bg-purple-500',
                                    },
                                    {
                                        value: stats.memberMetrics.adminCount,
                                        label: 'Admins',
                                        color: 'bg-blue-600 dark:bg-blue-500',
                                    },
                                    {
                                        value: stats.memberMetrics.memberCount,
                                        label: 'Members',
                                        color: 'bg-gray-400 dark:bg-gray-500',
                                    },
                                ]}
                            />
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="flex items-center justify-center gap-1 text-purple-600 dark:text-purple-400">
                                        <Shield className="h-4 w-4" />
                                        <span className="text-lg font-semibold">
                                            {stats.memberMetrics.ownerCount}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Owners
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                        {stats.memberMetrics.adminCount}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Admins
                                    </span>
                                </div>
                                <div>
                                    <div className="text-lg font-semibold text-gray-600 dark:text-gray-400">
                                        {stats.memberMetrics.memberCount}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Members
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Activity Feed */}
                <ActivityFeed
                    organizationSlug={organization.slug}
                    recentReleases={stats.activityFeed.recentReleases}
                    recentSyncs={stats.activityFeed.recentSyncs}
                />

                {/* Getting Started Info Box */}
                <InfoBox
                    title="Getting Started"
                    description="This organization hosts private Composer packages. Add
                        packages from repositories, manage API tokens for
                        authentication, and configure your composer.json to use
                        this private registry."
                >
                    <div className="flex gap-2">
                        <Button size="sm" asChild>
                            <Link
                                href={`/organizations/${organization.slug}/packages`}
                            >
                                View Packages
                            </Link>
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={`/organizations/${organization.slug}/repositories`}
                            >
                                Add Repository
                            </Link>
                        </Button>
                    </div>
                </InfoBox>
            </div>
        </AppLayout>
    );
}
