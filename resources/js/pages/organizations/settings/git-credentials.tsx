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
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
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
    githubConnectUrl: string;
}

export default function GitCredentials({
    organization,
    credentials,
    providers,
    githubConnectUrl,
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
                    <p className="text-muted-foreground">
                        Configure authentication for Git providers to enable
                        repository syncing
                    </p>
                </div>
            </div>

            <div className="space-y-4">
                {credentials.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="font-medium">Configured Providers</h4>
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
                        <h4 className="font-medium">Available Providers</h4>
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
                                            variant="secondary"
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
                            <p className="text-muted-foreground">
                                All Git providers are configured.
                            </p>
                        </div>
                    )}
            </div>

            <InfoBox
                title="About Git Providers"
                description="Git provider credentials are required to sync packages from
 private repositories. Each provider requires different
 authentication methods. Credentials are encrypted and stored
 securely."
            />

            {(editingCredential || addingProvider) && (
                <GitCredentialDialog
                    organizationSlug={organization.slug}
                    organizationName={organization.name}
                    credential={editingCredential}
                    provider={addingProvider ?? editingCredential!.provider}
                    providers={providers}
                    githubConnectUrl={githubConnectUrl}
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

GitCredentials.layout = withOrganizationSettingsLayout;
