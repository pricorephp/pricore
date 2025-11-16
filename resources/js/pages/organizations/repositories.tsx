import GitProviderIcon from '@/components/git-provider-icon';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { DateTime } from 'luxon';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type RepositoryData = App.Domains.Repository.Contracts.Data.RepositoryData;

interface RepositoriesPageProps {
    organization: OrganizationData;
    repositories: RepositoryData[];
}

function getProviderBadgeColor(provider: string): string {
    const colors: Record<string, string> = {
        github: 'bg-gray-800 text-white hover:bg-gray-800',
        gitlab: 'bg-orange-600 text-white hover:bg-orange-600',
        bitbucket: 'bg-blue-600 text-white hover:bg-blue-600',
        git: 'bg-gray-600 text-white hover:bg-gray-600',
    };

    return colors[provider] || colors.git;
}

function getSyncStatusVariant(
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (!status) return 'secondary';
    if (status === 'ok') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}

export default function Repositories({
    organization,
    repositories,
}: RepositoriesPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
        {
            title: 'Repositories',
            href: `/organizations/${organization.slug}/repositories`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Repositories - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="Repositories"
                        description="Connected Git repositories for automatic package syncing"
                    />
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Repository
                    </Button>
                </div>

                {repositories.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            No repositories yet. Connect a Git repository to
                            automatically sync packages.
                        </p>
                        <Button className="mt-4" variant="outline">
                            <Plus className="mr-2 h-4 w-4" />
                            Connect Your First Repository
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {repositories.map((repo) => (
                            <Card
                                key={repo.uuid}
                                className="transition-colors hover:bg-accent/50"
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-start justify-between gap-2">
                                        <span className="text-base">
                                            {repo.name}
                                        </span>
                                        {repo.url ? (
                                            <a
                                                href={repo.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-block"
                                            >
                                                <Badge
                                                    className={getProviderBadgeColor(
                                                        repo.provider,
                                                    )}
                                                >
                                                    <GitProviderIcon
                                                        provider={repo.provider}
                                                        className="mr-0.5 size-3"
                                                    />
                                                    {repo.providerLabel}
                                                </Badge>
                                            </a>
                                        ) : (
                                            <Badge
                                                className={getProviderBadgeColor(
                                                    repo.provider,
                                                )}
                                            >
                                                <GitProviderIcon
                                                    provider={repo.provider}
                                                    className="mr-0.5 size-3"
                                                />
                                                {repo.providerLabel}
                                            </Badge>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {repo.repoIdentifier}
                                    </p>

                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-muted-foreground">
                                            Sync Status:
                                        </span>
                                        <Badge
                                            variant={getSyncStatusVariant(
                                                repo.syncStatus,
                                            )}
                                        >
                                            {repo.syncStatus || 'pending'}
                                        </Badge>
                                    </div>

                                    {repo.lastSyncedAt && (
                                        <div className="text-xs text-muted-foreground">
                                            Last synced{' '}
                                            {DateTime.fromISO(
                                                repo.lastSyncedAt,
                                            ).toRelative()}
                                        </div>
                                    )}

                                    {repo.packagesCount > 0 && (
                                        <div className="flex items-center gap-1.5 pt-2 text-xs text-muted-foreground">
                                            <Package className="h-3 w-3" />
                                            <span>
                                                {repo.packagesCount}{' '}
                                                {repo.packagesCount === 1
                                                    ? 'package'
                                                    : 'packages'}
                                            </span>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                        About Repositories
                    </p>
                    <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        Connect your Git repositories from GitHub, GitLab,
                        Bitbucket, or any Git server to automatically discover
                        and sync Composer packages. Repositories are monitored
                        for new versions and can be synced manually or via
                        webhooks.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
