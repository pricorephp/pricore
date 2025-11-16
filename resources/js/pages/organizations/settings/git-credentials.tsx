import GitCredentialDialog from '@/components/git-credential-dialog';
import GitProviderIcon from '@/components/git-provider-icon';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings-layout';
import { router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;
type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface GitCredentialsPageProps {
    organization: OrganizationData;
    credentials: GitCredentialData[];
    providers: Record<string, string>;
}

export default function GitCredentials({
    organization,
    credentials,
    providers,
}: GitCredentialsPageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingCredential, setEditingCredential] =
        useState<GitCredentialData | null>(null);
    const [addingProvider, setAddingProvider] = useState<string | null>(null);

    const configuredProviders = new Set(credentials.map((c) => c.provider));
    const availableProviders = Object.entries(providers).filter(
        ([provider]) => !configuredProviders.has(provider),
    );

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
                `Are you sure you want to remove credentials for ${credential.providerLabel}? This will prevent syncing repositories using this provider.`,
            )
        ) {
            router.delete(
                `/organizations/${organization.slug}/settings/git-credentials/${credential.uuid}`,
                {
                    preserveScroll: true,
                },
            );
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
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">Git Providers</h3>
                    <p className="text-sm text-muted-foreground">
                        Configure authentication for Git providers to enable
                        repository syncing
                    </p>
                </div>
            </div>

            <div className="space-y-4">
                {credentials.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="text-sm font-medium">
                            Configured Providers
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
                                                provider={credential.provider}
                                                className={`h-5 w-5 ${getProviderIconColor(credential.provider)}`}
                                            />
                                            <div>
                                                <CardTitle className="text-base">
                                                    {credential.providerLabel}
                                                </CardTitle>
                                                <CardDescription>
                                                    {credential.isConfigured
                                                        ? 'Credentials configured'
                                                        : 'No credentials set'}
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                handleEdit(credential)
                                            }
                                        >
                                            <Edit className="h-4 w-4" />
                                            Edit
                                        </Button>
                                        <Button
                                            variant="outline"
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
                        <h4 className="text-sm font-medium">
                            Available Providers
                        </h4>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {availableProviders.map(([provider, label]) => (
                                <Card
                                    key={provider}
                                    className={`cursor-pointer border-l-4 transition-colors hover:bg-accent/50 ${getProviderBorderColor(provider)}`}
                                    onClick={() => handleAdd(provider)}
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-3">
                                                <GitProviderIcon
                                                    provider={provider}
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
                            ))}
                        </div>
                    </div>
                )}

                {credentials.length === 0 &&
                    availableProviders.length === 0 && (
                        <div className="rounded-lg border border-dashed p-12 text-center">
                            <p className="text-sm text-muted-foreground">
                                All Git providers are configured.
                            </p>
                        </div>
                    )}
            </div>

            <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                    About Git Providers
                </p>
                <p className="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                    Git provider credentials are required to sync packages from
                    private repositories. Each provider requires different
                    authentication methods. Credentials are encrypted and stored
                    securely.
                </p>
            </div>

            {(editingCredential || addingProvider) && (
                <GitCredentialDialog
                    organizationSlug={organization.slug}
                    organizationName={organization.name}
                    credential={editingCredential}
                    provider={addingProvider || editingCredential?.provider}
                    providers={providers}
                    isOpen={dialogOpen}
                    onClose={() => {
                        setDialogOpen(false);
                        setEditingCredential(null);
                        setAddingProvider(null);
                    }}
                />
            )}
        </div>
    );
}

GitCredentials.layout = (page: React.ReactNode) => (
    <AppLayout>
        <OrganizationSettingsLayout>{page}</OrganizationSettingsLayout>
    </AppLayout>
);
