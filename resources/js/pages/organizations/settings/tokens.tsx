import type {
    AccessTokenData,
    OrganizationData,
} from '@/../../resources/types/generated';
import CreateTokenDialog from '@/components/create-token-dialog';
import HeadingSmall from '@/components/heading-small';
import RevokeTokenDialog from '@/components/revoke-token-dialog';
import TokenCreatedDialog from '@/components/token-created-dialog';
import TokenList from '@/components/token-list';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface TokensPageProps {
    organization: OrganizationData;
    tokens: AccessTokenData[];
    tokenCreated?: {
        plainToken: string;
        name: string;
        expires_at: string | null;
    };
}

export default function Tokens({
    organization,
    tokens,
    tokenCreated,
}: TokensPageProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [revokeDialogOpen, setRevokeDialogOpen] = useState(false);
    const [tokenCreatedDialogOpen, setTokenCreatedDialogOpen] = useState(false);
    const [selectedToken, setSelectedToken] = useState<{
        uuid: string;
        name: string;
    } | null>(null);

    // Show token created dialog when a new token is created
    useEffect(() => {
        if (tokenCreated) {
            setTokenCreatedDialogOpen(true);
        }
    }, [tokenCreated]);

    const handleRevoke = (uuid: string, name: string) => {
        setSelectedToken({ uuid, name });
        setRevokeDialogOpen(true);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
        {
            title: 'Settings',
            href: `/organizations/${organization.slug}/settings`,
        },
        {
            title: 'API Tokens',
            href: `/organizations/${organization.slug}/settings/tokens`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`API Tokens - ${organization.name}`} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="API Tokens"
                        description="Manage access tokens for Composer authentication"
                    />
                    <Button onClick={() => setCreateDialogOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Token
                    </Button>
                </div>

                <div className="rounded-lg border bg-card p-6">
                    <TokenList tokens={tokens} onRevoke={handleRevoke} />
                </div>

                <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                        About API Tokens
                    </p>
                    <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        API tokens allow you to authenticate Composer requests
                        to access private packages in this organization. Each
                        token can be configured with an expiration date for
                        security.
                    </p>
                </div>
            </div>

            <CreateTokenDialog
                organizationSlug={organization.slug}
                isOpen={createDialogOpen}
                onClose={() => setCreateDialogOpen(false)}
            />

            {selectedToken && (
                <RevokeTokenDialog
                    tokenUuid={selectedToken.uuid}
                    tokenName={selectedToken.name}
                    organizationSlug={organization.slug}
                    isOpen={revokeDialogOpen}
                    onClose={() => {
                        setRevokeDialogOpen(false);
                        setSelectedToken(null);
                    }}
                />
            )}

            {tokenCreated && (
                <TokenCreatedDialog
                    token={tokenCreated.plainToken}
                    name={tokenCreated.name}
                    expiresAt={tokenCreated.expires_at}
                    organizationSlug={organization.slug}
                    isOpen={tokenCreatedDialogOpen}
                    onClose={() => setTokenCreatedDialogOpen(false)}
                />
            )}
        </AppLayout>
    );
}
