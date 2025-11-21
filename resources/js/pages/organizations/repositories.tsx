import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import AddRepositoryDialog from '@/components/add-repository-dialog';
import GitProviderIcon from '@/components/git-provider-icon';
import HeadingSmall from '@/components/heading-small';
import InfoBox from '@/components/info-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
    const [isDialogOpen, setIsDialogOpen] = useState(false);

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
                    <Button onClick={() => setIsDialogOpen(true)}>
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
                        <Button
                            className="mt-4"
                            variant="outline"
                            onClick={() => setIsDialogOpen(true)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Connect Your First Repository
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {repositories.map((repo) => (
                            <Link
                                key={repo.uuid}
                                href={show.url([organization.slug, repo.uuid])}
                            >
                                <Card className="transition-colors hover:bg-accent/50">
                                    <CardHeader>
                                        <CardTitle className="flex items-start justify-between gap-2">
                                            <span className="text-base">
                                                {repo.name}
                                            </span>
                                            {repo.url ? (
                                                <button
                                                    type="button"
                                                    className="inline-block"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        e.preventDefault();
                                                        window.open(
                                                            repo.url!,
                                                            '_blank',
                                                            'noopener,noreferrer',
                                                        );
                                                    }}
                                                >
                                                    <Badge
                                                        className={getProviderBadgeColor(
                                                            repo.provider,
                                                        )}
                                                    >
                                                        <GitProviderIcon
                                                            provider={
                                                                repo.provider
                                                            }
                                                            className="mr-0.5 size-3"
                                                        />
                                                        {repo.providerLabel}
                                                    </Badge>
                                                </button>
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
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-muted-foreground">
                                                Sync Status:
                                            </span>
                                            <Badge
                                                variant={getSyncStatusVariant(
                                                    repo.syncStatus,
                                                )}
                                            >
                                                {repo.syncStatusLabel ??
                                                    'Pending'}
                                            </Badge>
                                        </div>

                                        <div className="text-xs text-muted-foreground">
                                            Last synced:{' '}
                                            {repo.lastSyncedAt
                                                ? DateTime.fromISO(
                                                      repo.lastSyncedAt,
                                                  ).toRelative()
                                                : '—'}
                                        </div>
                                    </CardContent>
                                </Card>
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
            </div>
        </AppLayout>
    );
}
