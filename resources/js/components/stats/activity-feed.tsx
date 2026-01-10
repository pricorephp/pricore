import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { GitBranch, Package, RefreshCw } from 'lucide-react';
import { DateTime } from 'luxon';

type RecentReleaseData =
    App.Domains.Organization.Contracts.Data.RecentReleaseData;
type RecentSyncData = App.Domains.Organization.Contracts.Data.RecentSyncData;

interface ActivityFeedProps {
    organizationSlug: string;
    recentReleases: RecentReleaseData[];
    recentSyncs: RecentSyncData[];
}

function getSyncStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'success' | 'outline' {
    if (status === 'success') return 'success';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}

export function ActivityFeed({
    organizationSlug,
    recentReleases,
    recentSyncs,
}: ActivityFeedProps) {
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader className="flex flex-row items-center gap-2">
                    <Package className="h-4 w-4 text-muted-foreground" />
                    <CardTitle className="text-base">Recent Releases</CardTitle>
                </CardHeader>
                <CardContent>
                    {recentReleases.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
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
                                                className="truncate font-mono text-sm hover:underline"
                                            >
                                                {release.packageName}
                                            </Link>
                                            <div className="flex items-center gap-2">
                                                <code className="text-xs text-muted-foreground">
                                                    {release.version}
                                                </code>
                                                {release.isStable ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        Stable
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        Dev
                                                    </Badge>
                                                )}
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
                    <CardTitle className="text-base">Recent Syncs</CardTitle>
                </CardHeader>
                <CardContent>
                    {recentSyncs.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No sync activity yet
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {recentSyncs.slice(0, 5).map((sync, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between border-b border-border pb-2 last:border-0"
                                >
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={`/organizations/${organizationSlug}/repositories/${sync.repositoryUuid}`}
                                            className="flex items-center gap-1 truncate text-sm hover:underline"
                                        >
                                            <GitBranch className="h-3.5 w-3.5" />
                                            {sync.repositoryName}
                                        </Link>
                                        <div className="mt-0.5 flex items-center gap-2">
                                            <Badge
                                                variant={getSyncStatusVariant(
                                                    sync.status,
                                                )}
                                                className="text-xs"
                                            >
                                                {sync.statusLabel}
                                            </Badge>
                                            {sync.versionsAdded > 0 && (
                                                <span className="text-xs text-green-600 dark:text-green-400">
                                                    +{sync.versionsAdded}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {DateTime.fromISO(
                                            sync.startedAt as unknown as string,
                                        ).toRelative()}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
