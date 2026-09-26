import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { DateTime } from 'luxon';

type RepositorySyncStatus =
    App.Domains.Repository.Contracts.Enums.RepositorySyncStatus;
type SyncStatus = App.Domains.Repository.Contracts.Enums.SyncStatus;

export type StatusTone = 'success' | 'danger' | 'pending' | 'neutral';

export function statusTone(
    status: RepositorySyncStatus | SyncStatus | null,
): StatusTone {
    if (status === 'ok' || status === 'success') return 'success';
    if (status === 'failed') return 'danger';
    if (status === 'pending') return 'pending';
    return 'neutral';
}

const statusToneClasses: Record<StatusTone, string> = {
    success: 'bg-emerald-500',
    danger: 'bg-red-500',
    pending: 'bg-amber-500',
    neutral: 'bg-muted-foreground/30',
};

export function StatusDot({
    tone,
    className,
}: {
    tone: StatusTone;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'size-2 shrink-0 rounded-full',
                statusToneClasses[tone],
                className,
            )}
        />
    );
}

const SYNC_HEALTH_SLOTS = 10;

interface SyncHealthStripProps {
    /** Newest first, as returned by the API. */
    syncs: Array<{
        uuid: string;
        status: SyncStatus;
        statusLabel: string;
        startedAt: string;
    }>;
    showLabel?: boolean;
    className?: string;
}

export function SyncHealthStrip({
    syncs,
    showLabel = true,
    className,
}: SyncHealthStripProps) {
    const recent = syncs.slice(0, SYNC_HEALTH_SLOTS).reverse();
    const padding = SYNC_HEALTH_SLOTS - recent.length;

    return (
        <div className={cn('flex shrink-0 flex-col gap-1.5', className)}>
            <div className="flex items-end gap-1">
                {Array.from({ length: padding }, (_, index) => (
                    <span
                        key={`empty-${index}`}
                        className="h-5 w-1.5 rounded-full bg-muted"
                    />
                ))}
                {recent.map((sync) => (
                    <Tooltip key={sync.uuid}>
                        <TooltipTrigger asChild>
                            <span
                                className={cn(
                                    'h-5 w-1.5 rounded-full',
                                    statusToneClasses[statusTone(sync.status)],
                                )}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {sync.statusLabel} &middot;{' '}
                            {DateTime.fromISO(sync.startedAt).toRelative()}
                        </TooltipContent>
                    </Tooltip>
                ))}
            </div>
            {showLabel && (
                <span className="text-xs text-muted-foreground">
                    Last {SYNC_HEALTH_SLOTS} syncs
                </span>
            )}
        </div>
    );
}
