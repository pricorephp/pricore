import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    GitBranch,
    KeyRound,
    type LucideIcon,
    Mail,
    Package,
    PackageMinus,
    PackagePlus,
    RefreshCw,
    Shield,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { DateTime } from 'luxon';
import type { ReactNode } from 'react';

type ActivityLogData = App.Domains.Activity.Contracts.Data.ActivityLogData;

const iconMap: Record<string, LucideIcon> = {
    'git-branch-plus': GitBranch,
    'git-branch': GitBranch,
    'refresh-cw': RefreshCw,
    'alert-circle': AlertCircle,
    'package-plus': PackagePlus,
    'package-minus': PackageMinus,
    'user-plus': UserPlus,
    'user-minus': UserMinus,
    shield: Shield,
    mail: Mail,
    'key-round': KeyRound,
};

const categoryStyles: Record<string, { badge: string; standalone: string }> = {
    repository: {
        badge: 'bg-blue-500 text-white dark:bg-blue-500',
        standalone:
            'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400',
    },
    package: {
        badge: 'bg-purple-500 text-white dark:bg-purple-500',
        standalone:
            'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400',
    },
    member: {
        badge: 'bg-emerald-500 text-white dark:bg-emerald-500',
        standalone:
            'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400',
    },
    token: {
        badge: 'bg-amber-500 text-white dark:bg-amber-500',
        standalone:
            'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400',
    },
};

function getInitials(name: string): string {
    const parts = name.trim().split(' ');
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}

function getSubjectUrl(
    organizationSlug: string,
    subjectType: string | null,
    subjectUuid: string | null,
): string | null {
    if (!subjectType || !subjectUuid) return null;

    switch (subjectType) {
        case 'repository':
            return `/organizations/${organizationSlug}/repositories/${subjectUuid}`;
        case 'package':
            return `/organizations/${organizationSlug}/packages/${subjectUuid}`;
        case 'access_token':
            return `/organizations/${organizationSlug}/settings/tokens`;
        case 'user':
            return `/organizations/${organizationSlug}/settings/members`;
        default:
            return null;
    }
}

function formatDescription(
    activity: ActivityLogData,
    organizationSlug: string,
): { actor: string | null; action: ReactNode } {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const props = (activity.properties ?? {}) as Record<string, any>;
    const actor = activity.actorName ?? null;
    const subjectUrl = getSubjectUrl(
        organizationSlug,
        activity.subjectType,
        activity.subjectUuid,
    );

    const subject = (text: string) => {
        if (subjectUrl) {
            return (
                <Link
                    href={subjectUrl}
                    className="font-medium text-foreground underline decoration-foreground/20 underline-offset-2 hover:decoration-foreground/50"
                >
                    {text}
                </Link>
            );
        }
        return <span className="font-medium text-foreground">{text}</span>;
    };

    const bold = (text: string) => (
        <span className="font-medium text-foreground">{text}</span>
    );

    const verb = (text: string) =>
        actor ? text : text.charAt(0).toUpperCase() + text.slice(1);

    switch (activity.type) {
        case 'repository.added':
            return {
                actor,
                action: (
                    <>
                        {verb('added')} repository {subject(props.name)}
                    </>
                ),
            };
        case 'repository.removed':
            return {
                actor,
                action: (
                    <>
                        {verb('removed')} repository {bold(props.name)}
                    </>
                ),
            };
        case 'repository.synced': {
            const parts: string[] = [];
            if (props.versions_added > 0) {
                parts.push(
                    `${props.versions_added} ${props.versions_added === 1 ? 'version' : 'versions'} added`,
                );
            }
            if (props.versions_updated > 0) {
                parts.push(`${props.versions_updated} updated`);
            }
            if (props.versions_removed > 0) {
                parts.push(`${props.versions_removed} removed`);
            }
            return {
                actor: null,
                action: (
                    <>
                        {subject(props.name)} synced successfully
                        {parts.length > 0 && (
                            <span className="text-muted-foreground">
                                {' '}
                                &mdash; {parts.join(', ')}
                            </span>
                        )}
                    </>
                ),
            };
        }
        case 'repository.sync_failed':
            return {
                actor: null,
                action: (
                    <>
                        {subject(props.name)} sync{' '}
                        <span className="text-destructive">failed</span>
                    </>
                ),
            };
        case 'package.created':
            return {
                actor,
                action: (
                    <>
                        {verb('added')} package {subject(props.name)}
                    </>
                ),
            };
        case 'package.removed':
            return {
                actor,
                action: (
                    <>
                        {verb('removed')} package {bold(props.name)}
                    </>
                ),
            };
        case 'member.added':
            return {
                actor: null,
                action: (
                    <>
                        {subject(props.member_name)} joined as{' '}
                        {bold(props.role)}
                    </>
                ),
            };
        case 'member.removed':
            return {
                actor,
                action: (
                    <>
                        {verb('removed')} {bold(props.member_name)}
                    </>
                ),
            };
        case 'member.role_changed':
            return {
                actor,
                action: (
                    <>
                        {verb('changed')} {subject(props.member_name)}&apos;s
                        role to {bold(props.new_role)}
                    </>
                ),
            };
        case 'invitation.sent':
            return {
                actor,
                action: (
                    <>
                        {verb('invited')} {bold(props.email)} as{' '}
                        {bold(props.role)}
                    </>
                ),
            };
        case 'token.created':
            return {
                actor,
                action: (
                    <>
                        {verb('created')} token {subject(props.name)}
                    </>
                ),
            };
        case 'token.revoked':
            return {
                actor,
                action: (
                    <>
                        {verb('revoked')} token {bold(props.name)}
                    </>
                ),
            };
        default:
            return { actor, action: <>{activity.typeLabel}</> };
    }
}

function ActivityAvatar({ activity }: { activity: ActivityLogData }) {
    const Icon = iconMap[activity.icon] ?? Package;
    const styles =
        categoryStyles[activity.category] ?? categoryStyles.repository;
    const hasActor = !!activity.actorName;

    if (hasActor) {
        return (
            <div className="relative shrink-0">
                <Avatar className="size-9">
                    <AvatarImage
                        src={activity.actorAvatar ?? undefined}
                        alt={activity.actorName ?? ''}
                    />
                    <AvatarFallback className="bg-gradient-to-br from-neutral-200 to-neutral-300 text-xs text-neutral-600 dark:from-neutral-600 dark:to-neutral-700 dark:text-neutral-300">
                        {getInitials(activity.actorName ?? '')}
                    </AvatarFallback>
                </Avatar>
                <div
                    className={cn(
                        'absolute -right-0.5 -bottom-0.5 flex size-4.5 items-center justify-center rounded-full ring-2 ring-background',
                        styles.badge,
                    )}
                >
                    <Icon className="size-2.5" />
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-full',
                styles.standalone,
            )}
        >
            <Icon className="size-4.5" />
        </div>
    );
}

function ActivityTimelineItem({
    activity,
    organizationSlug,
}: {
    activity: ActivityLogData;
    organizationSlug: string;
}) {
    const { actor, action } = formatDescription(activity, organizationSlug);

    return (
        <div className="flex gap-3.5 py-3">
            <ActivityAvatar activity={activity} />
            <div className="min-w-0 flex-1 pt-0.5">
                <p className="text-[0.9375rem] leading-snug text-muted-foreground">
                    {actor && (
                        <span className="font-medium text-foreground">
                            {actor}{' '}
                        </span>
                    )}
                    {action}
                </p>
                <p className="mt-1 text-sm text-muted-foreground/70">
                    {DateTime.fromISO(
                        activity.createdAt as unknown as string,
                    ).toRelative()}
                </p>
            </div>
        </div>
    );
}

function ActivityTimelineSkeleton() {
    return (
        <div className="divide-y divide-border">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex gap-3.5 py-3">
                    <Skeleton className="size-9 shrink-0 rounded-full" />
                    <div className="flex-1 pt-0.5">
                        <Skeleton className="h-5 w-4/5" />
                        <Skeleton className="mt-1 h-4 w-16" />
                    </div>
                </div>
            ))}
        </div>
    );
}

interface ActivityTimelineProps {
    organizationSlug: string;
    activities: ActivityLogData[] | undefined;
}

export function ActivityTimeline({
    organizationSlug,
    activities,
}: ActivityTimelineProps) {
    return (
        <div>
            <div className="flex items-center gap-2 px-2 pb-3">
                <Activity className="h-4 w-4 text-muted-foreground" />
                <h3 className="text-base font-semibold">Activity Feed</h3>
            </div>
            {activities === undefined ? (
                <ActivityTimelineSkeleton />
            ) : activities.length === 0 ? (
                <p className="px-2 text-sm text-muted-foreground">
                    No activity yet. Activity will appear here as you add
                    repositories, invite members, and manage tokens.
                </p>
            ) : (
                <div className="divide-y divide-border">
                    {activities.map((activity) => (
                        <ActivityTimelineItem
                            key={activity.uuid}
                            activity={activity}
                            organizationSlug={organizationSlug}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
