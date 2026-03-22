import {
    show,
    store,
} from '@/actions/App/Domains/Mirror/Http/Controllers/MirrorController';
import InfoBox from '@/components/info-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardList } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOrganizationChannel } from '@/hooks/use-organization-channel';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Form, Link } from '@inertiajs/react';
import { ArrowUpRight, Copy, Plus } from 'lucide-react';
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
                                <Copy className="h-5 w-5 text-muted-foreground" />
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
                description="Registry mirrors let you import packages from external Composer registries (e.g. Satis repositories) into your organization. Packages are synced automatically and served alongside your other packages."
            />

            <AddMirrorDialog
                organizationSlug={organization.slug}
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

function AddMirrorDialog({
    organizationSlug,
    isOpen,
    onClose,
}: {
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
}) {
    const [authType, setAuthType] = useState('none');

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                    setAuthType('none');
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Add Registry Mirror</DialogTitle>
                <DialogDescription>
                    Add an external Composer registry to mirror packages from.
                    The registry must serve a packages.json file.
                </DialogDescription>

                <Form
                    action={store.url(organizationSlug)}
                    method="post"
                    onSuccess={() => {
                        onClose();
                        setAuthType('none');
                    }}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid space-y-2">
                                <Label htmlFor="name">
                                    Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="e.g., Internal Satis"
                                />
                                {errors.name && (
                                    <p className="text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="url">
                                    Registry URL{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="url"
                                    name="url"
                                    required
                                    placeholder="https://satis.example.com"
                                />
                                {errors.url && (
                                    <p className="text-destructive">
                                        {errors.url}
                                    </p>
                                )}
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="auth_type">
                                    Authentication
                                </Label>
                                <Select
                                    name="auth_type"
                                    value={authType}
                                    onValueChange={setAuthType}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select authentication type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            None
                                        </SelectItem>
                                        <SelectItem value="basic">
                                            HTTP Basic
                                        </SelectItem>
                                        <SelectItem value="bearer">
                                            Bearer Token
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.auth_type && (
                                    <p className="text-destructive">
                                        {errors.auth_type}
                                    </p>
                                )}
                            </div>

                            {authType === 'basic' && (
                                <>
                                    <div className="grid space-y-2">
                                        <Label htmlFor="username">
                                            Username{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="username"
                                            name="username"
                                            required
                                        />
                                        {errors.username && (
                                            <p className="text-destructive">
                                                {errors.username}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid space-y-2">
                                        <Label htmlFor="password">
                                            Password{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            required
                                        />
                                        {errors.password && (
                                            <p className="text-destructive">
                                                {errors.password}
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}

                            {authType === 'bearer' && (
                                <div className="grid space-y-2">
                                    <Label htmlFor="token">
                                        Token{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="token"
                                        name="token"
                                        type="password"
                                        required
                                    />
                                    {errors.token && (
                                        <p className="text-destructive">
                                            {errors.token}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center space-x-2">
                                <input
                                    type="hidden"
                                    name="mirror_dist"
                                    value="0"
                                />
                                <Checkbox
                                    id="mirror_dist"
                                    name="mirror_dist"
                                    defaultChecked
                                    value="1"
                                />
                                <Label
                                    htmlFor="mirror_dist"
                                    className="font-normal"
                                >
                                    Mirror dist archives
                                </Label>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => {
                                        onClose();
                                        setAuthType('none');
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding...' : 'Add Mirror'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Mirrors.layout = withOrganizationSettingsLayout;
