import CreateTokenDialog from '@/components/create-token-dialog';
import RevokeTokenDialog from '@/components/revoke-token-dialog';
import TokenCreatedDialog from '@/components/token-created-dialog';
import TokenList from '@/components/token-list';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings-layout';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type AccessTokenData = App.Domains.Token.Contracts.Data.AccessTokenData;
type TokenCreatedData = App.Domains.Token.Contracts.Data.TokenCreatedData;
type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface TokensPageProps {
    organization: OrganizationData;
    tokens: AccessTokenData[];
    tokenCreated?: TokenCreatedData;
}

export default function Tokens({
    organization,
    tokens,
    tokenCreated,
}: TokensPageProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [revokeDialogOpen, setRevokeDialogOpen] = useState(false);
    const [tokenCreatedDialogOpen, setTokenCreatedDialogOpen] =
        useState(!!tokenCreated);
    const [selectedToken, setSelectedToken] = useState<{
        uuid: string;
        name: string;
    } | null>(null);
    const previousTokenPlainTokenRef = useRef<string | undefined>(undefined);

    // Open dialog when a new token is created and close create dialog
    useEffect(() => {
        if (tokenCreated) {
            const currentPlainToken = tokenCreated.plainToken;
            const previousPlainToken = previousTokenPlainTokenRef.current;

            // If this is a new token (different from what we've seen before)
            if (currentPlainToken !== previousPlainToken) {
                previousTokenPlainTokenRef.current = currentPlainToken;
                // Schedule state updates to avoid synchronous setState in effect
                setTimeout(() => {
                    setCreateDialogOpen(false);
                    setTokenCreatedDialogOpen(true);
                }, 0);
            }
        } else {
            // Reset ref when tokenCreated is cleared
            previousTokenPlainTokenRef.current = undefined;
            setTimeout(() => {
                setTokenCreatedDialogOpen(false);
            }, 0);
        }
    }, [tokenCreated]);

    const handleRevoke = (uuid: string, name: string) => {
        setSelectedToken({ uuid, name });
        setRevokeDialogOpen(true);
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">API Tokens</h3>
                    <p className="text-sm text-muted-foreground">
                        Manage access tokens for Composer authentication
                    </p>
                </div>
                <Button onClick={() => setCreateDialogOpen(true)}>
                    <Plus className="h-4 w-4" />
                    Create Token
                </Button>
            </div>

            <div className="rounded-lg border bg-card p-4">
                <TokenList tokens={tokens} onRevoke={handleRevoke} />
            </div>

            <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-950">
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                    About API Tokens
                </p>
                <p className="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                    API tokens allow you to authenticate Composer requests to
                    access private packages in this organization. Each token can
                    be configured with an expiration date for security.
                </p>
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
                    expiresAt={tokenCreated.expiresAt}
                    isOpen={tokenCreatedDialogOpen}
                    onClose={() => setTokenCreatedDialogOpen(false)}
                />
            )}
        </div>
    );
}

Tokens.layout = (page: React.ReactNode) => (
    <AppLayout>
        <OrganizationSettingsLayout>{page}</OrganizationSettingsLayout>
    </AppLayout>
);
