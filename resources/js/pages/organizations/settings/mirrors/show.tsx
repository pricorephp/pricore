import {
    destroy,
    index,
} from '@/actions/App/Domains/Mirror/Http/Controllers/MirrorController';
import SyncMirrorController from '@/actions/App/Domains/Mirror/Http/Controllers/SyncMirrorController';
import { show as showPackage } from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import HeadingSmall from '@/components/heading-small';
import PackageCard from '@/components/package-card';
import { RelativeTime } from '@/components/relative-time';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardList } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { useOrganizationChannel } from '@/hooks/use-organization-channel';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Copy,
    EllipsisVertical,
    Loader2,
    Package,
    PackageCheck,
    PackageMinus,
    PackagePlus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type MirrorData = App.Domains.Mirror.Contracts.Data.MirrorData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;
type MirrorSyncLogData = App.Domains.Mirror.Contracts.Data.MirrorSyncLogData;
type RepositorySyncStatus =
    App.Domains.Repository.Contracts.Enums.RepositorySyncStatus;
type SyncStatus = App.Domains.Repository.Contracts.Enums.SyncStatus;

interface MirrorShowProps {
    organization: OrganizationData;
    mirror: MirrorData;
    packages: PackageData[];
    syncLogs: MirrorSyncLogData[];
}

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

export default function MirrorShow({
    organization,
    mirror,
    packages,
    syncLogs,
}: MirrorShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    useOrganizationChannel(organization.uuid);

    const [selectedLog, setSelectedLog] = useState<MirrorSyncLogData | null>(
        null,
    );
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Settings',
            href: `/organizations/${organization.slug}/settings/general`,
        },
        {
            title: 'Registry Mirrors',
            href: index.url(organization.slug),
        },
        {
            title: mirror.name,
            href: '#',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${mirror.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <HeadingSmall
                            title={mirror.name}
                            description={mirror.url}
                        />
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">
                                <Copy className="mr-0.5 size-3" />
                                Mirror
                            </Badge>
                            {mirror.syncStatus && (
                                <Badge
                                    variant={getSyncStatusVariant(
                                        mirror.syncStatus,
                                    )}
                                >
                                    {mirror.syncStatus === 'ok'
                                        ? 'OK'
                                        : mirror.syncStatus === 'failed'
                                          ? 'Failed'
                                          : 'Pending'}
                                </Badge>
                            )}
                            {mirror.mirrorDist && (
                                <Badge variant="outline">Dist mirroring</Badge>
                            )}
                        </div>
                        {mirror.lastSyncedAt && (
                            <p className="text-muted-foreground">
                                Last synced{' '}
                                <RelativeTime datetime={mirror.lastSyncedAt} />
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
                                        SyncMirrorController.url([
                                            organization.slug,
                                            mirror.uuid,
                                        ]),
                                    )
                                }
                            >
                                <RefreshCw />
                                Sync Now
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onSelect={() => setDeleteDialogOpen(true)}
                            >
                                <Trash2 />
                                Remove Mirror
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="space-y-4">
                    <HeadingSmall
                        title="Packages"
                        description={
                            packages.length > 0
                                ? `${packages.length} package${packages.length === 1 ? '' : 's'} imported from this mirror`
                                : 'Packages imported from this mirror will appear here'
                        }
                    />
                    {packages.length > 0 ? (
                        <CardList>
                            {packages.map((pkg) => (
                                <Link
                                    key={pkg.uuid}
                                    href={showPackage.url([
                                        organization.slug,
                                        pkg.uuid,
                                    ])}
                                    className="group flex items-center justify-between px-4 py-3 transition-colors hover:bg-accent/50"
                                >
                                    <PackageCard package={pkg} hideRepository />
                                </Link>
                            ))}
                        </CardList>
                    ) : (
                        <Card>
                            <CardContent className="py-8 text-center">
                                <Package className="mx-auto mb-3 size-8 text-muted-foreground" />
                                <p className="font-medium">No packages found</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    No packages have been imported from this
                                    mirror yet. Try syncing to import packages.
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
                        <CardList>
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
                                        <span className="font-medium">
                                            {log.statusLabel}
                                        </span>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <RelativeTime
                                                datetime={log.startedAt}
                                            />
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
                        </CardList>
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

                <Dialog
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                >
                    <DialogContent>
                        <DialogTitle>Remove Mirror</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove{' '}
                            <strong>{mirror.name}</strong>? Packages that were
                            imported from this mirror will remain but will no
                            longer be synced.
                        </DialogDescription>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    router.delete(
                                        destroy.url([
                                            organization.slug,
                                            mirror.uuid,
                                        ]),
                                    );
                                    setDeleteDialogOpen(false);
                                }}
                            >
                                Remove
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
