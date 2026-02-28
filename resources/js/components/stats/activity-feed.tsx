import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    GitBranch,
    Loader2,
    Package,
    PackageCheck,
    PackageMinus,
    PackagePlus,
    RefreshCw,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type RecentReleaseData =
    App.Domains.Organization.Contracts.Data.RecentReleaseData;
type RecentSyncData = App.Domains.Organization.Contracts.Data.RecentSyncData;

interface ActivityFeedProps {
    organizationSlug: string;
    recentReleases: RecentReleaseData[];
    recentSyncs: RecentSyncData[];
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

export function ActivityFeed({
    organizationSlug,
    recentReleases,
    recentSyncs,
}: ActivityFeedProps) {
    const [selectedSync, setSelectedSync] = useState<RecentSyncData | null>(
        null,
    );

    return (
        <>
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader className="flex flex-row items-center gap-2">
                        <Package className="h-4 w-4 text-muted-foreground" />
                        <CardTitle className="text-base">
                            Recent Releases
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentReleases.length === 0 ? (
                            <p className="text-muted-foreground">
                                No releases yet
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {recentReleases
                                    .slice(0, 5)
                                    .map((release, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between border-b border-border pb-2 last:border-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={`/organizations/${organizationSlug}/packages/${release.packageUuid}`}
                                                    className="truncate font-mono hover:underline"
                                                >
                                                    {release.packageName}
                                                </Link>
                                                <div className="flex items-center gap-2">
                                                    <code
                                                        className="max-w-64 truncate text-xs text-muted-foreground"
                                                        title={release.version}
                                                    >
                                                        {release.version}
                                                    </code>
                                                </div>
                                            </div>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {release.releasedAt
                                                    ? DateTime.fromISO(
                                                          release.releasedAt as unknown as string,
                                                      ).toRelative()
                                                    : 'Unknown'}
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2">
                        <RefreshCw className="h-4 w-4 text-muted-foreground" />
                        <CardTitle className="text-base">
                            Recent Syncs
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentSyncs.length === 0 ? (
                            <p className="text-muted-foreground">
                                No sync activity yet
                            </p>
                        ) : (
                            <div className="space-y-0.5">
                                {recentSyncs.slice(0, 5).map((sync, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-3 border-b border-border py-2 last:border-0"
                                    >
                                        <div className="shrink-0">
                                            {sync.status === 'success' && (
                                                <CheckCircle2 className="size-5 text-emerald-500" />
                                            )}
                                            {sync.status === 'failed' && (
                                                <AlertCircle className="size-5 text-destructive" />
                                            )}
                                            {sync.status === 'pending' && (
                                                <Loader2 className="size-5 animate-spin text-muted-foreground" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={`/organizations/${organizationSlug}/repositories/${sync.repositoryUuid}`}
                                                className="flex items-center gap-1 truncate text-sm font-medium hover:underline"
                                            >
                                                <GitBranch className="size-3.5 shrink-0" />
                                                {sync.repositoryName}
                                            </Link>
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <span>
                                                    {DateTime.fromISO(
                                                        sync.startedAt as unknown as string,
                                                    ).toRelative()}
                                                </span>
                                                <span>&middot;</span>
                                                <span>
                                                    {formatDuration(
                                                        sync.startedAt as unknown as string,
                                                        sync.completedAt as unknown as
                                                            | string
                                                            | null,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            {sync.status !== 'failed' && (
                                                <div className="flex items-center gap-2.5 text-sm tabular-nums">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className={cn(
                                                                    'inline-flex items-center gap-1',
                                                                    sync.versionsAdded >
                                                                        0
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-muted-foreground/40',
                                                                )}
                                                            >
                                                                <PackagePlus className="size-3.5" />
                                                                +
                                                                {
                                                                    sync.versionsAdded
                                                                }
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {sync.versionsAdded}{' '}
                                                            {sync.versionsAdded ===
                                                            1
                                                                ? 'version'
                                                                : 'versions'}{' '}
                                                            added
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className={cn(
                                                                    'inline-flex items-center gap-1',
                                                                    sync.versionsUpdated >
                                                                        0
                                                                        ? 'text-amber-600 dark:text-amber-400'
                                                                        : 'text-muted-foreground/40',
                                                                )}
                                                            >
                                                                <PackageCheck className="size-3.5" />
                                                                {
                                                                    sync.versionsUpdated
                                                                }
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {
                                                                sync.versionsUpdated
                                                            }{' '}
                                                            {sync.versionsUpdated ===
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
                                                                    'inline-flex items-center gap-1',
                                                                    sync.versionsRemoved >
                                                                        0
                                                                        ? 'text-red-600 dark:text-red-400'
                                                                        : 'text-muted-foreground/40',
                                                                )}
                                                            >
                                                                <PackageMinus className="size-3.5" />
                                                                -
                                                                {
                                                                    sync.versionsRemoved
                                                                }
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {
                                                                sync.versionsRemoved
                                                            }{' '}
                                                            {sync.versionsRemoved ===
                                                            1
                                                                ? 'version'
                                                                : 'versions'}{' '}
                                                            removed
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            )}
                                            {sync.errorMessage && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedSync(sync)
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
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={!!selectedSync}
                onOpenChange={(open) => !open && setSelectedSync(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Sync Error</DialogTitle>
                        <DialogDescription>
                            {selectedSync?.repositoryName}
                            {' — '}
                            {selectedSync &&
                                DateTime.fromISO(
                                    selectedSync.startedAt as unknown as string,
                                ).toLocaleString(DateTime.DATETIME_SHORT)}
                        </DialogDescription>
                    </DialogHeader>
                    <pre className="max-h-80 overflow-auto rounded-md bg-muted p-4 text-sm whitespace-pre-wrap">
                        {selectedSync?.errorMessage}
                    </pre>
                </DialogContent>
            </Dialog>
        </>
    );
}
