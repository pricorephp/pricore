import HeadingSmall from '@/components/heading-small';
import InfoBox from '@/components/info-box';
import { ActivityFeed } from '@/components/stats/activity-feed';
import { StatCard } from '@/components/stats/stat-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, usePage } from '@inertiajs/react';
import { Box, GitBranch, Key, Users } from 'lucide-react';

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
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/repositories`}
                    >
                        <StatCard
                            title="Repositories"
                            value={stats.repositoriesCount}
                            icon={GitBranch}
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/settings/tokens`}
                    >
                        <StatCard
                            title="API Tokens"
                            value={stats.tokensCount}
                            icon={Key}
                        />
                    </Link>

                    <Link
                        href={`/organizations/${organization.slug}/settings/members`}
                    >
                        <StatCard
                            title="Members"
                            value={stats.membersCount}
                            icon={Users}
                        />
                    </Link>
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
