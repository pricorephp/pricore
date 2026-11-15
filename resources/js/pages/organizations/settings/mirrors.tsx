import { show } from '@/actions/App/Domains/Mirror/Http/Controllers/MirrorController';
import {
    AddMirrorDialog,
    isPackagistUrl,
} from '@/components/add-mirror-dialog';
import InfoBox from '@/components/info-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardList } from '@/components/ui/card';
import { useOrganizationChannel } from '@/hooks/use-organization-channel';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Boxes, Copy, Plus } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type MirrorData = App.Domains.Mirror.Contracts.Data.MirrorData;
type RepositorySyncStatus =
    App.Domains.Repository.Contracts.Enums.RepositorySyncStatus;

interface MirrorsPageProps {
    organization: OrganizationData;
    mirrors: MirrorData[];
}

function getSyncStatusVariant(
    status: RepositorySyncStatus | null,
): 'default' | 'secondary' | 'destructive' | 'success' | 'outline' {
    if (!status) return 'secondary';
    if (status === 'ok') return 'success';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}

function getSyncStatusLabel(status: RepositorySyncStatus | null): string {
    if (!status) return 'Not synced';
    if (status === 'ok') return 'OK';
    if (status === 'failed') return 'Failed';
    return 'Pending';
}

export default function Mirrors({ organization, mirrors }: MirrorsPageProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);

    useOrganizationChannel(organization.uuid);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">Registry Mirrors</h3>
                    <p className="text-muted-foreground">
                        Mirror packages from external Composer registries into
                        this organization
                    </p>
                </div>
                <Button onClick={() => setCreateDialogOpen(true)}>
                    <Plus className="h-4 w-4" />
                    Add Mirror
                </Button>
            </div>

            {mirrors.length > 0 ? (
                <CardList>
                    {mirrors.map((mirror) => (
                        <Link
                            key={mirror.uuid}
                            href={show.url([organization.slug, mirror.uuid])}
                            className="group flex items-center justify-between px-4 py-3 transition-colors hover:bg-accent/50"
                        >
                            <div className="flex items-center gap-3">
                                {isPackagistUrl(mirror.url) ? (
                                    <Boxes className="h-5 w-5 text-muted-foreground" />
                                ) : (
                                    <Copy className="h-5 w-5 text-muted-foreground" />
                                )}
                                <div className="space-y-1">
                                    <span className="font-medium transition-colors group-hover:text-primary">
                                        {mirror.name}
                                    </span>
                                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                        <Badge
                                            variant={getSyncStatusVariant(
                                                mirror.syncStatus,
                                            )}
                                        >
                                            {getSyncStatusLabel(
                                                mirror.syncStatus,
                                            )}
                                        </Badge>
                                        {mirror.syncMode === 'on_demand' && (
                                            <Badge variant="outline">
                                                On demand
                                            </Badge>
                                        )}
                                        <span>
                                            {mirror.packagesCount}{' '}
                                            {mirror.packagesCount === 1
                                                ? 'package'
                                                : 'packages'}
                                        </span>
                                        <span>
                                            Last synced:{' '}
                                            {mirror.lastSyncedAt
                                                ? DateTime.fromISO(
                                                      mirror.lastSyncedAt,
                                                  ).toRelative()
                                                : 'Never'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <ArrowUpRight className="h-4 w-4 shrink-0 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
                        </Link>
                    ))}
                </CardList>
            ) : (
                <EmptyState />
            )}

            <InfoBox
                title="About Registry Mirrors"
                description="Registry mirrors import packages from other Composer registries, such as Packagist or a Satis instance, into your organization. They are kept in sync and served alongside your other packages."
            />

            <AddMirrorDialog
                organizationSlug={organization.slug}
                hasPackagistMirror={mirrors.some((mirror) =>
                    isPackagistUrl(mirror.url),
                )}
                isOpen={createDialogOpen}
                onClose={() => setCreateDialogOpen(false)}
            />
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-12">
            <Copy className="mb-4 h-10 w-10 text-muted-foreground" />
            <h4 className="text-base font-medium">No registry mirrors yet</h4>
            <p className="mt-1 text-sm text-muted-foreground">
                Add an external Composer registry to mirror its packages.
            </p>
        </div>
    );
}

Mirrors.layout = withOrganizationSettingsLayout;
