import GitCredentialDialog from '@/components/git-credential-dialog';
import GitProviderIcon from '@/components/git-provider-icon';
import InfoBox from '@/components/info-box';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editProfile } from '@/routes/profile';
import { gitCredentials as gitCredentialsRoute } from '@/routes/settings';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialsPageProps {
    credentials: GitCredentialData[];
    providers: Record<string, string>;
    githubConnectUrl: string;
    gitlabConnectUrl: string;
}

function describeCredential(credential: GitCredentialData): string {
    const parts = [credential.providerLabel];

    if (credential.name && credential.baseUrl) {
        parts.push(credential.baseUrl.replace(/^https?:\/\//, ''));
    }

    if (!credential.isConfigured) {
        parts.push('No credentials set');
    } else if (credential.repositoriesCount > 0) {
        parts.push(
            `${credential.repositoriesCount} ${credential.repositoriesCount === 1 ? 'repository' : 'repositories'}`,
        );
    }

    return parts.join(' · ');
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

export default function GitCredentials({
    credentials,
    providers,
    githubConnectUrl,
    gitlabConnectUrl,
}: GitCredentialsPageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingCredential, setEditingCredential] =
        useState<GitCredentialData | null>(null);
    const [addingProvider, setAddingProvider] = useState<string | null>(null);

    const availableProviders = Object.entries(providers);

    const handleAdd = (provider: string) => {
        setAddingProvider(provider);
        setEditingCredential(null);
        setDialogOpen(true);
    };

    const handleEdit = (credential: GitCredentialData) => {
        setEditingCredential(credential);
        setAddingProvider(null);
        setDialogOpen(true);
    };

    const handleDelete = (credential: GitCredentialData) => {
        if (
            confirm(
                credential.repositoriesCount > 0
                    ? `Are you sure you want to remove ${credential.label}? ${credential.repositoriesCount} ${credential.repositoriesCount === 1 ? 'repository uses' : 'repositories use'} these credentials and will stop syncing.`
                    : `Are you sure you want to remove ${credential.label}?`,
            )
        ) {
            router.delete(`/settings/git-credentials/${credential.uuid}`, {
                preserveScroll: true,
            });
        }
    };

    const getProviderIconColor = (provider: string): string => {
        const colors: Record<string, string> = {
            github: 'text-gray-800',
            gitlab: 'text-orange-600',
            bitbucket: 'text-blue-600',
            git: 'text-gray-600',
        };

        return colors[provider] || colors.git;
    };

    const getProviderBorderColor = (provider: string): string => {
        const colors: Record<string, string> = {
            github: 'border-l-gray-800',
            gitlab: 'border-l-orange-600',
            bitbucket: 'border-l-blue-600',
            git: 'border-l-gray-600',
        };

        return colors[provider] || colors.git;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium">
                                Git Providers
                            </h3>
                            <p className="text-muted-foreground">
                                Configure authentication for Git providers to
                                enable repository syncing
                            </p>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {credentials.length > 0 && (
                            <div className="space-y-3">
                                <h4 className="font-medium">
                                    Configured Credentials
                                </h4>
                                {credentials.map((credential) => (
                                    <Card
                                        key={credential.uuid}
                                        className={`border-l-4 ${getProviderBorderColor(credential.provider)}`}
                                    >
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <GitProviderIcon
                                                        provider={
                                                            credential.provider
                                                        }
                                                        className={`h-5 w-5 ${getProviderIconColor(credential.provider)}`}
                                                    />
                                                    <div>
                                                        <CardTitle className="text-base">
                                                            {credential.label}
                                                        </CardTitle>
                                                        <CardDescription>
                                                            {describeCredential(
                                                                credential,
                                                            )}
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleEdit(credential)
                                                    }
                                                >
                                                    <Edit className="h-4 w-4" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleDelete(credential)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Remove
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        {availableProviders.length > 0 && (
                            <div className="space-y-3">
                                <h4 className="font-medium">Add Credentials</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {availableProviders.map(
                                        ([provider, label]) => (
                                            <Card
                                                key={provider}
                                                className={`cursor-pointer border-l-4 transition-colors hover:bg-accent/50 ${getProviderBorderColor(provider)}`}
                                                onClick={() =>
                                                    handleAdd(provider)
                                                }
                                            >
                                                <CardHeader>
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <GitProviderIcon
                                                                provider={
                                                                    provider
                                                                }
                                                                className={`h-5 w-5 ${getProviderIconColor(provider)}`}
                                                            />
                                                            <CardTitle className="text-base">
                                                                {label}
                                                            </CardTitle>
                                                        </div>
                                                    </div>
                                                </CardHeader>
                                                <CardContent>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="w-full"
                                                    >
                                                        <Plus className="h-4 w-4" />
                                                        Add Credentials
                                                    </Button>
                                                </CardContent>
                                            </Card>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    <InfoBox
                        title="About Git Providers"
                        description="Git provider credentials are required to sync packages from
 private repositories. Add credentials more than once to use several
 accounts or self-hosted instances of the same provider, and pick
 one when adding a repository. Credentials are encrypted and stored
 securely."
                    />

                    {(editingCredential || addingProvider) && (
                        <GitCredentialDialog
                            credential={editingCredential}
                            provider={
                                addingProvider ?? editingCredential!.provider
                            }
                            providers={providers}
                            githubConnectUrl={githubConnectUrl}
                            gitlabConnectUrl={gitlabConnectUrl}
                            isOpen={dialogOpen}
                            onClose={() => {
                                setDialogOpen(false);
                                setEditingCredential(null);
                                setAddingProvider(null);
                            }}
                        />
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
