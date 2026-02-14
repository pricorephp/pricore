import { show } from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import { edit } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import SyncRepository from '@/actions/App/Domains/Repository/Http/Controllers/SyncRepositoryController';
import SyncWebhook from '@/actions/App/Domains/Repository/Http/Controllers/SyncWebhookController';
import GitProviderIcon from '@/components/git-provider-icon';
import HeadingSmall from '@/components/heading-small';
import PackageCard from '@/components/package-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { EllipsisVertical, RefreshCw, Settings, Webhook } from 'lucide-react';
import { DateTime } from 'luxon';

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
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'success' | 'outline' {
    if (!status) return 'secondary';
    if (status === 'ok') return 'success';
    if (status === 'failed') return 'destructive';
    return 'secondary';
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
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
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
                                    {repository.syncStatusLabel ?? 'Pending'}
                                </Badge>
                            )}
                            {repository.provider === 'github' && (
                                <Badge
                                    variant={
                                        repository.webhookActive
                                            ? 'success'
                                            : 'outline'
                                    }
                                >
                                    <Webhook className="mr-0.5 size-3" />
                                    {repository.webhookActive
                                        ? 'Webhook Active'
                                        : 'No Webhook'}
                                </Badge>
                            )}
                        </div>
                        {repository.lastSyncedAt && (
                            <p className="text-muted-foreground">
                                Last synced{' '}
                                {DateTime.fromISO(
                                    repository.lastSyncedAt,
                                ).toRelative()}
                            </p>
                        )}
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="secondary">
                                Actions
                                <EllipsisVertical className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(
                                        SyncRepository.url([
                                            organization.slug,
                                            repository.uuid,
                                        ]),
                                    )
                                }
                            >
                                <RefreshCw />
                                Sync Now
                            </DropdownMenuItem>
                            {repository.provider === 'github' && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            SyncWebhook.url([
                                                organization.slug,
                                                repository.uuid,
                                            ]),
                                        )
                                    }
                                >
                                    <Webhook />
                                    {repository.webhookActive
                                        ? 'Re-register Webhook'
                                        : 'Register Webhook'}
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link
                                    href={edit.url({
                                        organization: organization.slug,
                                        repository: repository.uuid,
                                    })}
                                >
                                    <Settings />
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {packages.length > 0 && (
                    <div className="space-y-4">
                        <HeadingSmall
                            title="Linked Packages"
                            description={`${packages.length} package${packages.length === 1 ? '' : 's'} discovered from this repository`}
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {packages.map((pkg) => (
                                <Link
                                    key={pkg.uuid}
                                    href={show.url([
                                        organization.slug,
                                        pkg.uuid,
                                    ])}
                                >
                                    <PackageCard package={pkg} hideRepository />
                                </Link>
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
                            <CardContent className="py-8 text-center text-muted-foreground">
                                No sync history yet. Click "Sync Now" to perform
                                the first synchronization.
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="rounded-lg border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="w-1/8">
                                            Status
                                        </TableHead>
                                        <TableHead className="w-1/8">
                                            Started
                                        </TableHead>
                                        <TableHead className="w-1/8">
                                            Duration
                                        </TableHead>
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
                                                <div className="">
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
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
