import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Box, GitBranch, Key } from 'lucide-react';

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title={organization.name}
                        description="Organization overview and quick actions"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Packages
                            </CardTitle>
                            <Box className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.packagesCount}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                <Link
                                    href={`/organizations/${organization.slug}/packages`}
                                    className="text-primary hover:underline"
                                >
                                    View all packages →
                                </Link>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Repositories
                            </CardTitle>
                            <GitBranch className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.repositoriesCount}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                <Link
                                    href={`/organizations/${organization.slug}/repositories`}
                                    className="text-primary hover:underline"
                                >
                                    View all repositories →
                                </Link>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                API Tokens
                            </CardTitle>
                            <Key className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.tokensCount}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                <Link
                                    href={`/organizations/${organization.slug}/settings/tokens`}
                                    className="text-primary hover:underline"
                                >
                                    Manage tokens →
                                </Link>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                        Getting Started
                    </p>
                    <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        This organization hosts private Composer packages. Add
                        packages from repositories, manage API tokens for
                        authentication, and configure your composer.json to use
                        this private registry.
                    </p>
                    <div className="mt-3 flex gap-2">
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
                </div>
            </div>
        </AppLayout>
    );
}
