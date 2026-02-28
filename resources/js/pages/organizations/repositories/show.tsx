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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    EllipsisVertical,
    Loader2,
    Package,
    PackageCheck,
    PackageMinus,
    PackagePlus,
    RefreshCw,
    Settings,
    Webhook,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

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
    canManageRepository: boolean;
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

type RepositorySyncStatus =
    App.Domains.Repository.Contracts.Enums.RepositorySyncStatus;
type SyncStatus = App.Domains.Repository.Contracts.Enums.SyncStatus;

function getSyncStatusVariant(
    status: RepositorySyncStatus | SyncStatus | null,
): 'default' | 'secondary' | 'destructive' | 'success' | 'outline' {
    if (!status) return 'secondary';
    if (status === 'ok' || status === 'success') return 'success';
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
    canManageRepository,
}: RepositoryShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const [selectedLog, setSelectedLog] = useState<SyncLogData | null>(null);

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
                                        className={cn(
                                            getProviderBadgeColor(
                                                repository.provider,
                                            ),
                                            'border-transparent',
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
                                    className={cn(
                                        getProviderBadgeColor(
                                            repository.provider,
                                        ),
                                        'border-transparent',
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
                            {canManageRepository && (
                                <>
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
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="space-y-4">
                    <HeadingSmall
                        title="Packages"
                        description={
                            packages.length > 0
                                ? `${packages.length} package${packages.length === 1 ? '' : 's'} discovered from this repository`
                                : 'Packages discovered from this repository will appear here'
                        }
                    />
                    {packages.length > 0 ? (
                        <div className="divide-y divide-border rounded-lg border bg-card">
                            {packages.map((pkg) => (
                                <Link
                                    key={pkg.uuid}
                                    href={show.url([
                                        organization.slug,
                                        pkg.uuid,
                                    ])}
                                    className="group flex items-center justify-between px-4 py-3 transition-colors hover:bg-accent/50"
                                >
                                    <PackageCard package={pkg} hideRepository />
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="py-8 text-center">
                                <Package className="mx-auto mb-3 size-8 text-muted-foreground" />
                                <p className="font-medium">No packages found</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    No packages have been discovered from this
                                    repository yet. Try syncing to discover
                                    packages.
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

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
                        <div className="divide-y divide-border rounded-lg border bg-card">
                            {syncLogs.map((log) => (
                                <div
                                    key={log.uuid}
                                    className="flex items-center gap-3 px-4 py-3"
                                >
                                    <div>
                                        {log.status === 'success' && (
                                            <CheckCircle2 className="size-5 text-emerald-500" />
                                        )}
                                        {log.status === 'failed' && (
                                            <AlertCircle className="size-5 text-destructive" />
                                        )}
                                        {log.status === 'pending' && (
                                            <Loader2 className="size-5 animate-spin text-muted-foreground" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <span className="text-sm font-medium">
                                            {log.statusLabel}
                                        </span>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <span>
                                                {DateTime.fromISO(
                                                    log.startedAt,
                                                ).toRelative()}
                                            </span>
                                            <span>&middot;</span>
                                            <span>
                                                {formatDuration(
                                                    log.startedAt,
                                                    log.completedAt,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        {log.status !== 'failed' && (
                                            <div className="flex items-center gap-2.5 text-sm tabular-nums">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1.5',
                                                                log.versionsAdded >
                                                                    0
                                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                                    : 'text-muted-foreground/40',
                                                            )}
                                                        >
                                                            <PackagePlus className="size-4" />
                                                            +{log.versionsAdded}
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {log.versionsAdded}{' '}
                                                        {log.versionsAdded === 1
                                                            ? 'version'
                                                            : 'versions'}{' '}
                                                        added
                                                    </TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1.5',
                                                                log.versionsUpdated >
                                                                    0
                                                                    ? 'text-amber-600 dark:text-amber-400'
                                                                    : 'text-muted-foreground/40',
                                                            )}
                                                        >
                                                            <PackageCheck className="size-4" />
                                                            {
                                                                log.versionsUpdated
                                                            }
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {log.versionsUpdated}{' '}
                                                        {log.versionsUpdated ===
                                                        1
                                                            ? 'version'
                                                            : 'versions'}{' '}
                                                        updated
                                                    </TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1.5',
                                                                log.versionsRemoved >
                                                                    0
                                                                    ? 'text-red-600 dark:text-red-400'
                                                                    : 'text-muted-foreground/40',
                                                            )}
                                                        >
                                                            <PackageMinus className="size-4" />
                                                            -
                                                            {
                                                                log.versionsRemoved
                                                            }
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {log.versionsRemoved}{' '}
                                                        {log.versionsRemoved ===
                                                        1
                                                            ? 'version'
                                                            : 'versions'}{' '}
                                                        removed
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        )}
                                        {log.errorMessage && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSelectedLog(log)
                                                }
                                                className="text-sm text-destructive underline underline-offset-2 hover:text-destructive/80"
                                            >
                                                View error
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
                <Dialog
                    open={!!selectedLog}
                    onOpenChange={(open) => !open && setSelectedLog(null)}
                >
                    <DialogContent className="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Sync Error</DialogTitle>
                            <DialogDescription>
                                {selectedLog &&
                                    DateTime.fromISO(
                                        selectedLog.startedAt,
                                    ).toLocaleString(DateTime.DATETIME_SHORT)}
                            </DialogDescription>
                        </DialogHeader>
                        <pre className="max-h-80 overflow-auto rounded-md bg-muted p-4 text-sm whitespace-pre-wrap">
                            {selectedLog?.errorMessage}
                        </pre>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
