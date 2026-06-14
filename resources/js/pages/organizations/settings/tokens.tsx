import {
    destroy,
    store,
    update,
} from '@/actions/App/Domains/Token/Http/Controllers/TokenController';
import { CopyButton } from '@/components/copy-button';
import CreateTokenDialog from '@/components/create-token-dialog';
import EditTokenDialog from '@/components/edit-token-dialog';
import InfoBox from '@/components/info-box';
import RevokeTokenDialog from '@/components/revoke-token-dialog';
import TokenCreatedDialog from '@/components/token-created-dialog';
import TokenList from '@/components/token-list';
import { Button } from '@/components/ui/button';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
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
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingToken, setEditingToken] = useState<AccessTokenData | null>(
        null,
    );
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

    const handleEdit = (token: AccessTokenData) => {
        setEditingToken(token);
        setEditDialogOpen(true);
    };

    const composerRepoCommand = `composer config repositories.${organization.slug} composer ${organization.composerRepositoryUrl}`;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">Access Tokens</h3>
                    <p className="text-muted-foreground">
                        Shared tokens for this organization's packages and
                        automation
                    </p>
                </div>
                <Button onClick={() => setCreateDialogOpen(true)}>
                    <Plus className="h-4 w-4" />
                    Create Token
                </Button>
            </div>

            <div className="space-y-2 rounded-lg border bg-card p-4">
                <div className="space-y-0.5">
                    <p className="font-medium">Registry URL</p>
                    <p className="text-sm text-muted-foreground">
                        Run this once per project to register{' '}
                        {organization.name} as a Composer repository.
                    </p>
                </div>
                <div className="flex items-center gap-2 rounded-md border bg-muted/50 px-3 py-2">
                    <code className="flex-1 truncate font-mono text-sm">
                        {composerRepoCommand}
                    </code>
                    <CopyButton text={composerRepoCommand} />
                </div>
            </div>

            <div className="rounded-lg border bg-card px-4 py-2">
                <TokenList
                    tokens={tokens}
                    onEdit={handleEdit}
                    onRevoke={handleRevoke}
                />
            </div>

            <InfoBox
                title="About Organization Tokens"
                description={`Organization tokens belong to ${organization.name} and are shared by everyone who can manage its settings — ideal for CI pipelines and servers, and they keep working after a member leaves. They install this organization's packages with Composer and can act on it through the Pricore API. For a token tied to you personally and usable across all your organizations, use your personal access tokens.`}
            />

            <CreateTokenDialog
                storeUrl={store.url(organization.slug)}
                description={`A shared token for the ${organization.name} organization.`}
                isOpen={createDialogOpen}
                onClose={() => setCreateDialogOpen(false)}
            />

            {editingToken && (
                <EditTokenDialog
                    updateUrl={update.url([
                        organization.slug,
                        editingToken.uuid,
                    ])}
                    token={editingToken}
                    isOpen={editDialogOpen}
                    onClose={() => {
                        setEditDialogOpen(false);
                        setEditingToken(null);
                    }}
                />
            )}

            {selectedToken && (
                <RevokeTokenDialog
                    tokenName={selectedToken.name}
                    deleteUrl={destroy.url([
                        organization.slug,
                        selectedToken.uuid,
                    ])}
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
                    scopes={tokenCreated.scopes}
                    isOpen={tokenCreatedDialogOpen}
                    onClose={() => setTokenCreatedDialogOpen(false)}
                />
            )}
        </div>
    );
}

Tokens.layout = withOrganizationSettingsLayout;
