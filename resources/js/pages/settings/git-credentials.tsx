import GitCredentialDialog from '@/components/git-credential-dialog';
import GitProviderIcon, {
    getProviderColor,
} from '@/components/git-provider-icon';
import InfoBox from '@/components/info-box';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { sshKeys } from '@/routes/organizations/settings';
import { edit as editProfile } from '@/routes/profile';
import { gitCredentials as gitCredentialsRoute } from '@/routes/settings';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialsPageProps {
    credentials: GitCredentialData[];
    providers: Record<string, string>;
    githubConnectUrl: string;
    gitlabConnectUrl: string;
    gitlabInstanceHost: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: editProfile().url,
    },
    {
        title: 'Git Providers',
        href: gitCredentialsRoute().url,
    },
];

const providerHints: Record<string, string> = {
    github: 'GitHub account or personal access token',
    gitlab: 'GitLab.com or a self-hosted instance',
    bitbucket: 'Atlassian account with an API token',
};

// Unnamed GitLab credentials are already labelled by their host
function credentialHost(credential: GitCredentialData): string | null {
    if (credential.provider !== 'gitlab' || !credential.name) {
        return null;
    }

    if (!credential.baseUrl) {
        return 'gitlab.com';
    }

    try {
        return new URL(credential.baseUrl).host;
    } catch {
        return credential.baseUrl;
    }
}

function repositoriesLabel(count: number): string {
    if (count === 0) {
        return 'Not used by any repository';
    }

    return `Used by ${count} ${count === 1 ? 'repository' : 'repositories'}`;
}

type DialogState =
    | { mode: 'add'; provider: string }
    | { mode: 'edit'; credential: GitCredentialData }
    | null;

export default function GitCredentials({
    credentials,
    providers,
    githubConnectUrl,
    gitlabConnectUrl,
    gitlabInstanceHost,
}: GitCredentialsPageProps) {
    const { auth } = usePage<SharedData>().props;
    const sshKeyOrganizations = auth.organizations.filter(
        (organization) => organization.permissions?.canViewSettings,
    );
    const [dialog, setDialog] = useState<DialogState>(null);
    const [removing, setRemoving] = useState<GitCredentialData | null>(null);

    const confirmRemove = () => {
        if (!removing) {
            return;
        }

        router.delete(`/settings/git-credentials/${removing.uuid}`, {
            preserveScroll: true,
            onFinish: () => setRemoving(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Git Providers" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h3 className="text-lg font-medium">Git Providers</h3>
                        <p className="text-muted-foreground">
                            Accounts Pricore uses to read your private
                            repositories. Connect as many accounts or
                            self-hosted instances as you need.
                        </p>
                    </div>

                    <div className="space-y-4">
                        {Object.entries(providers).map(([provider, label]) => {
                            const providerCredentials = credentials.filter(
                                (credential) =>
                                    credential.provider === provider,
                            );

                            return (
                                <section
                                    key={provider}
                                    className="rounded-lg border bg-card"
                                >
                                    <header className="flex items-center justify-between gap-4 px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-8 items-center justify-center rounded-md border bg-surface-inset">
                                                <GitProviderIcon
                                                    provider={provider}
                                                    className={cn(
                                                        'size-4',
                                                        getProviderColor(
                                                            provider,
                                                        ),
                                                    )}
                                                />
                                            </div>
                                            <div>
                                                <h4 className="font-medium">
                                                    {label}
                                                </h4>
                                                <p className="text-xs text-muted-foreground">
                                                    {providerHints[provider]}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() =>
                                                setDialog({
                                                    mode: 'add',
                                                    provider,
                                                })
                                            }
                                        >
                                            <Plus className="size-4" />
                                            {providerCredentials.length > 0
                                                ? 'Add account'
                                                : 'Connect'}
                                        </Button>
                                    </header>

                                    {providerCredentials.length > 0 && (
                                        <ul className="divide-y border-t">
                                            {providerCredentials.map(
                                                (credential) => (
                                                    <li
                                                        key={credential.uuid}
                                                        className="group/credential flex items-center justify-between gap-4 px-4 py-2.5"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <span className="truncate font-medium">
                                                                    {
                                                                        credential.label
                                                                    }
                                                                </span>
                                                                {credentialHost(
                                                                    credential,
                                                                ) && (
                                                                    <span className="truncate font-mono text-xs text-muted-foreground">
                                                                        {credentialHost(
                                                                            credential,
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p className="text-xs text-muted-foreground">
                                                                {repositoriesLabel(
                                                                    credential.repositoriesCount,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <div className="flex shrink-0 items-center gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        mode: 'edit',
                                                                        credential,
                                                                    })
                                                                }
                                                                aria-label={`Edit ${credential.label}`}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    setRemoving(
                                                                        credential,
                                                                    )
                                                                }
                                                                aria-label={`Remove ${credential.label}`}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </div>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </section>
                            );
                        })}

                        <section className="rounded-lg border bg-card">
                            <header className="flex items-center justify-between gap-4 px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-8 items-center justify-center rounded-md border bg-surface-inset">
                                        <GitProviderIcon
                                            provider="git"
                                            className={cn(
                                                'size-4',
                                                getProviderColor('git'),
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <h4 className="font-medium">
                                            Other Git servers
                                        </h4>
                                        <p className="text-xs text-muted-foreground">
                                            Gitea, Forgejo or any self-hosted
                                            server over SSH
                                        </p>
                                    </div>
                                </div>
                                {sshKeyOrganizations.length === 1 && (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        asChild
                                    >
                                        <Link
                                            href={sshKeys(
                                                sshKeyOrganizations[0].slug,
                                            )}
                                        >
                                            <KeyRound className="size-4" />
                                            SSH keys
                                        </Link>
                                    </Button>
                                )}
                            </header>
                            <div className="border-t px-4 py-3 text-muted-foreground">
                                These don't need an account here. Add an SSH key
                                to your organization, give its public key read
                                access on the server, and add the repository
                                with the <strong>Generic Git</strong> provider.
                                {sshKeyOrganizations.length > 1 && (
                                    <span className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                        <span>SSH keys:</span>
                                        {sshKeyOrganizations.map(
                                            (organization) => (
                                                <Link
                                                    key={organization.uuid}
                                                    href={sshKeys(
                                                        organization.slug,
                                                    )}
                                                    className="text-primary hover:underline"
                                                >
                                                    {organization.name}
                                                </Link>
                                            ),
                                        )}
                                    </span>
                                )}
                            </div>
                        </section>
                    </div>

                    <InfoBox
                        title="How credentials are used"
                        description="When you add a repository you pick which account Pricore syncs it with, and it keeps syncing with that account for everyone in the organization. Tokens are encrypted and only visible to you."
                    />
                </div>

                {dialog && (
                    <GitCredentialDialog
                        credential={
                            dialog.mode === 'edit' ? dialog.credential : null
                        }
                        provider={
                            dialog.mode === 'edit'
                                ? dialog.credential.provider
                                : dialog.provider
                        }
                        providers={providers}
                        githubConnectUrl={githubConnectUrl}
                        gitlabConnectUrl={gitlabConnectUrl}
                        gitlabInstanceHost={gitlabInstanceHost}
                        isOpen
                        onClose={() => setDialog(null)}
                    />
                )}

                <Dialog
                    open={removing !== null}
                    onOpenChange={(open) => !open && setRemoving(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove {removing?.label}</DialogTitle>
                            <DialogDescription>
                                {removing && removing.repositoriesCount > 0
                                    ? `${repositoriesLabel(removing.repositoriesCount)}. ${removing.repositoriesCount === 1 ? 'It stops' : 'They stop'} syncing until ${removing.repositoriesCount === 1 ? 'it is' : 'they are'} added again with another account.`
                                    : 'Pricore will no longer be able to use this account.'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="secondary"
                                onClick={() => setRemoving(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmRemove}
                            >
                                Remove
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
