import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import AddRepositoryDialog from '@/components/add-repository-dialog';
import { EmptyState } from '@/components/empty-state';
import GitProviderIcon from '@/components/git-provider-icon';
import HeadingSmall from '@/components/heading-small';
import ImportRepositoriesDialog from '@/components/import-repositories-dialog';
import InfoBox from '@/components/info-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, GitBranch, Import, Plus } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type RepositoryData = App.Domains.Repository.Contracts.Data.RepositoryData;

interface RepositoriesPageProps {
    organization: OrganizationData;
    repositories: RepositoryData[];
    configuredProviders?: string[];
}

function getProviderIconColor(provider: string): string {
    const colors: Record<string, string> = {
        github: 'text-gray-800 dark:text-gray-300',
        gitlab: 'text-orange-600',
        bitbucket: 'text-blue-600',
        git: 'text-gray-600',
    };

    return colors[provider] || colors.git;
}

type RepositorySyncStatus =
    App.Domains.Repository.Contracts.Enums.RepositorySyncStatus;

function getSyncStatusVariant(
    status: RepositorySyncStatus | null,
): 'default' | 'secondary' | 'destructive' | 'success' | 'outline' {
    if (!status) return 'secondary';
    if (status === 'ok') return 'success';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}

export default function Repositories({
    organization,
    repositories,
    configuredProviders = [],
}: RepositoriesPageProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isImportOpen, setIsImportOpen] = useState(false);

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
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
                    <div className="flex gap-2">
                        {configuredProviders.length > 0 && (
                            <Button
                                variant="outline"
                                onClick={() => setIsImportOpen(true)}
                            >
                                <Import className="size-4" />
                                Import Repositories
                            </Button>
                        )}
                        <Button onClick={() => setIsDialogOpen(true)}>
                            <Plus className="size-4" />
                            Add Repository
                        </Button>
                    </div>
                </div>

                {repositories.length === 0 ? (
                    <EmptyState
                        icon={GitBranch}
                        title="No repositories yet"
                        description="Connect a Git repository to automatically sync and discover Composer packages."
                        action={{
                            label: 'Connect Your First Repository',
                            onClick: () => setIsDialogOpen(true),
                        }}
                    />
                ) : (
                    <div className="divide-y divide-border rounded-lg border bg-card">
                        {repositories.map((repo) => (
                            <Link
                                key={repo.uuid}
                                href={show.url([organization.slug, repo.uuid])}
                                className="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50"
                            >
                                <GitProviderIcon
                                    provider={repo.provider}
                                    className={`size-8 shrink-0 ${getProviderIconColor(repo.provider)}`}
                                />
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium transition-colors group-hover:text-primary">
                                            {repo.name}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                        <Badge
                                            variant={getSyncStatusVariant(
                                                repo.syncStatus,
                                            )}
                                        >
                                            {repo.syncStatusLabel ?? 'Pending'}
                                        </Badge>
                                        <span>
                                            Last synced:{' '}
                                            {repo.lastSyncedAt
                                                ? DateTime.fromISO(
                                                      repo.lastSyncedAt,
                                                  ).toRelative()
                                                : 'Never'}
                                        </span>
                                    </div>
                                </div>
                                <ArrowUpRight className="h-4 w-4 shrink-0 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
                            </Link>
                        ))}
                    </div>
                )}

                <InfoBox
                    title="About Repositories"
                    description="Connect your Git repositories from GitHub, GitLab,
 Bitbucket, or any Git server to automatically discover
 and sync Composer packages. Repositories are monitored
 for new versions and can be synced manually or via
 webhooks."
                />

                <AddRepositoryDialog
                    organizationSlug={organization.slug}
                    isOpen={isDialogOpen}
                    onClose={() => setIsDialogOpen(false)}
                    configuredProviders={configuredProviders}
                />

                {configuredProviders.length > 0 && (
                    <ImportRepositoriesDialog
                        organizationSlug={organization.slug}
                        isOpen={isImportOpen}
                        onClose={() => setIsImportOpen(false)}
                        configuredProviders={configuredProviders}
                    />
                )}
            </div>
        </AppLayout>
    );
}
