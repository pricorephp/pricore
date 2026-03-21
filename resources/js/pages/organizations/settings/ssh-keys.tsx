import {
    destroy,
    store,
} from '@/actions/App/Domains/Organization/Http/Controllers/SshKeyController';
import { CopyButton } from '@/components/copy-button';
import InfoBox from '@/components/info-box';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Form, router } from '@inertiajs/react';
import { KeyRound, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationSshKeyData =
    App.Domains.Organization.Contracts.Data.OrganizationSshKeyData;

interface SshKeysPageProps {
    organization: OrganizationData;
    sshKeys: OrganizationSshKeyData[];
}

export default function SshKeys({ organization, sshKeys }: SshKeysPageProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">SSH Keys</h3>
                    <p className="text-muted-foreground">
                        Manage SSH keys for Generic Git repository
                        authentication
                    </p>
                </div>
                <Button onClick={() => setCreateDialogOpen(true)}>
                    <Plus className="h-4 w-4" />
                    Generate SSH Key
                </Button>
            </div>

            {sshKeys.length > 0 ? (
                <div className="space-y-4">
                    {sshKeys.map((sshKey) => (
                        <SshKeyCard
                            key={sshKey.uuid}
                            sshKey={sshKey}
                            organizationSlug={organization.slug}
                        />
                    ))}
                </div>
            ) : (
                <EmptyState />
            )}

            <InfoBox
                title="About SSH Keys"
                description="SSH keys authenticate Pricore when fetching from private Git repositories. Generate a key here, then add the public key as a deploy key on your Git server. When adding a Generic Git repository, select which SSH key to use."
            />

            <GenerateSshKeyDialog
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
            <KeyRound className="mb-4 h-10 w-10 text-muted-foreground" />
            <h4 className="text-base font-medium">No SSH keys yet</h4>
            <p className="mt-1 text-sm text-muted-foreground">
                Generate an SSH key to authenticate with private Git
                repositories.
            </p>
        </div>
    );
}

function SshKeyCard({
    sshKey,
    organizationSlug,
}: {
    sshKey: OrganizationSshKeyData;
    organizationSlug: string;
}) {
    const [showPublicKey, setShowPublicKey] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const createdAt = new Date(sshKey.createdAt).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return (
        <div className="rounded-lg border bg-card p-4">
            <div className="flex items-start justify-between">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <KeyRound className="h-4 w-4 text-muted-foreground" />
                        <h4 className="font-medium">{sshKey.name}</h4>
                    </div>
                    <p className="font-mono text-sm text-muted-foreground">
                        {sshKey.fingerprint}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Created {createdAt}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowPublicKey(!showPublicKey)}
                    >
                        {showPublicKey ? 'Hide' : 'Show'} Public Key
                    </Button>
                    <Dialog
                        open={deleteDialogOpen}
                        onOpenChange={setDeleteDialogOpen}
                    >
                        <DialogTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Delete SSH Key</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete{' '}
                                <strong>{sshKey.name}</strong>? Repositories
                                using this key will no longer be able to
                                authenticate.
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
                                                organizationSlug,
                                                sshKey.uuid,
                                            ]),
                                        );
                                        setDeleteDialogOpen(false);
                                    }}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            {showPublicKey && (
                <div className="mt-3">
                    <div className="relative">
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 font-mono text-sm break-all whitespace-pre-wrap">
                            {sshKey.publicKey}
                        </pre>
                        <div className="absolute top-1.5 right-1.5">
                            <CopyButton text={sshKey.publicKey} />
                        </div>
                    </div>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        Add this public key as a deploy key on your Git server.
                    </p>
                </div>
            )}
        </div>
    );
}

function GenerateSshKeyDialog({
    organizationSlug,
    isOpen,
    onClose,
}: {
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogTitle>Generate SSH Key</DialogTitle>
                <DialogDescription>
                    Generate a new Ed25519 SSH key pair. The public key will be
                    displayed for you to add as a deploy key on your Git server.
                </DialogDescription>

                <Form
                    action={store.url(organizationSlug)}
                    method="post"
                    onSuccess={onClose}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid space-y-2">
                                <Label htmlFor="name">
                                    Key Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="e.g., GitHub Deploy Key"
                                />
                                {errors.name && (
                                    <p className="text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Generating...'
                                        : 'Generate Key'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

SshKeys.layout = withOrganizationSettingsLayout;
