import PackageCard from '@/components/package-card';
import GitProviderIcon from '@/components/git-provider-icon';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import SyncRepository from '@/actions/App/Domains/Repository/Http/Controllers/SyncRepositoryController';
import { Form, Head } from '@inertiajs/react';
import { DateTime } from 'luxon';
import { RefreshCw } from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type RepositoryData = App.Domains.Repository.Contracts.Data.RepositoryData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;
type SyncLogData = App.Domains.Repository.Contracts.Data.SyncLogData;

interface RepositoryShowProps {
    organization: OrganizationData;
    repository: RepositoryData;
    packages: PackageData[];
    syncLogs: SyncLogData[];
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
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'success') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'pending') return 'secondary';
    return 'secondary';
}

function getSyncStatusLabel(status: string): string {
    if (status === 'success') return 'Success';
    if (status === 'failed') return 'Failed';
    if (status === 'pending') return 'Pending';
    return status;
}

function formatDuration(startedAt: string, completedAt: string | null): string {
    if (!completedAt) return 'In progress...';

    const start = DateTime.fromISO(startedAt);
    const end = DateTime.fromISO(completedAt);
    const duration = end.diff(start);

    if (duration.as('seconds') < 60) {
        return `${Math.round(duration.as('seconds'))}s`;
    }

    return `${Math.round(duration.as('minutes'))}m ${Math.round(duration.as('seconds') % 60)}s`;
}

export default function RepositoryShow({
    organization,
    repository,
    packages,
    syncLogs,
}: RepositoryShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
        {
            title: 'Repositories',
            href: `/organizations/${organization.slug}/repositories`,
        },
        {
            title: repository.name,
            href: `/organizations/${organization.slug}/repositories/${repository.uuid}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${repository.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <HeadingSmall
                            title={repository.name}
                            description={repository.repoIdentifier}
                        />
                        <div className="flex items-center gap-2">
                            {repository.url ? (
                                <a
                                    href={repository.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-block"
                                >
                                    <Badge
                                        className={getProviderBadgeColor(
                                            repository.provider,
                                        )}
                                    >
                                        <GitProviderIcon
                                            provider={repository.provider}
                                            className="mr-0.5 size-3"
                                        />
                                        {repository.providerLabel}
                                    </Badge>
                                </a>
                            ) : (
                                <Badge
                                    className={getProviderBadgeColor(
                                        repository.provider,
                                    )}
                                >
                                    <GitProviderIcon
                                        provider={repository.provider}
                                        className="mr-0.5 size-3"
                                    />
                                    {repository.providerLabel}
                                </Badge>
                            )}
                            {repository.syncStatus && (
                                <Badge
                                    variant={getSyncStatusVariant(
                                        repository.syncStatus,
                                    )}
                                >
                                    {getSyncStatusLabel(repository.syncStatus)}
                                </Badge>
                            )}
                        </div>
                    </div>
                    <Form
                        action={SyncRepository.url([organization.slug, repository.uuid])}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                variant="outline"
                            >
                                <RefreshCw
                                    className={`mr-2 h-4 w-4 ${
                                        processing ? 'animate-spin' : ''
                                    }`}
                                />
                                {processing ? 'Syncing...' : 'Sync Now'}
                            </Button>
                        )}
                    </Form>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Packages
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {repository.packagesCount}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Linked packages from this repository
                            </p>
                        </CardContent>
                    </Card>

                    {repository.lastSyncedAt && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Last Synced
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-sm font-medium">
                                    {DateTime.fromISO(
                                        repository.lastSyncedAt,
                                    ).toRelative()}
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {DateTime.fromISO(
                                        repository.lastSyncedAt,
                                    ).toLocaleString(DateTime.DATETIME_MED)}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {packages.length > 0 && (
                    <div className="space-y-4">
                        <HeadingSmall
                            title="Linked Packages"
                            description={`${packages.length} package${packages.length === 1 ? '' : 's'} discovered from this repository`}
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {packages.map((pkg) => (
                                <PackageCard key={pkg.uuid} package={pkg} />
                            ))}
                        </div>
                    </div>
                )}

                <div className="space-y-4">
                    <HeadingSmall
                        title="Sync History"
                        description="Recent synchronization attempts and their results"
                    />
                    {syncLogs.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                No sync history yet. Click "Sync Now" to perform
                                the first synchronization.
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Versions Added</TableHead>
                                        <TableHead>Versions Updated</TableHead>
                                        <TableHead>Error</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {syncLogs.map((log) => (
                                        <TableRow key={log.uuid}>
                                            <TableCell>
                                                <Badge
                                                    variant={getSyncStatusVariant(
                                                        log.status,
                                                    )}
                                                >
                                                    {log.statusLabel}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    {DateTime.fromISO(
                                                        log.startedAt,
                                                    ).toRelative()}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {DateTime.fromISO(
                                                        log.startedAt,
                                                    ).toLocaleString(
                                                        DateTime.DATETIME_SHORT,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {formatDuration(
                                                    log.startedAt,
                                                    log.completedAt,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {log.versionsAdded}
                                            </TableCell>
                                            <TableCell>
                                                {log.versionsUpdated}
                                            </TableCell>
                                            <TableCell>
                                                {log.errorMessage ? (
                                                    <span className="text-xs text-destructive">
                                                        {log.errorMessage}
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

