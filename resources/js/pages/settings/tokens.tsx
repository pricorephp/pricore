import {
    destroy,
    store,
    update,
} from '@/actions/App/Domains/Token/Http/Controllers/UserTokenController';
import CreateTokenDialog from '@/components/create-token-dialog';
import EditTokenDialog from '@/components/edit-token-dialog';
import InfoBox from '@/components/info-box';
import RevokeTokenDialog from '@/components/revoke-token-dialog';
import TokenCreatedDialog from '@/components/token-created-dialog';
import TokenList from '@/components/token-list';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editProfile } from '@/routes/profile';
import { index as tokensIndex } from '@/routes/settings/tokens';
import { type BreadcrumbItem } from '@/types';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type AccessTokenData = App.Domains.Token.Contracts.Data.AccessTokenData;
type TokenCreatedData = App.Domains.Token.Contracts.Data.TokenCreatedData;

interface TokensPageProps {
    tokens: AccessTokenData[];
    tokenCreated?: TokenCreatedData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: editProfile().url,
    },
    {
        title: 'Access Tokens',
        href: tokensIndex().url,
    },
];

export default function Tokens({ tokens, tokenCreated }: TokensPageProps) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium">
                                Personal Access Tokens
                            </h3>
                            <p className="text-muted-foreground">
                                Install packages with Composer across every
                                organization you belong to
                            </p>
                        </div>
                        <Button onClick={() => setCreateDialogOpen(true)}>
                            <Plus className="h-4 w-4" />
                            Create Token
                        </Button>
                    </div>

                    <div className="rounded-lg border bg-card px-4 py-2">
                        <TokenList
                            tokens={tokens}
                            onEdit={handleEdit}
                            onRevoke={handleRevoke}
                        />
                    </div>

                    <InfoBox
                        title="About Personal Access Tokens"
                        description="Personal tokens act as you across every organization you belong to — for installing packages with Composer and for the Pricore API. For a token limited to a single organization, visit that organization's token settings."
                    />

                    <CreateTokenDialog
                        storeUrl={store.url()}
                        description="Acts as you across every organization you belong to."
                        isOpen={createDialogOpen}
                        onClose={() => setCreateDialogOpen(false)}
                    />

                    {editingToken && (
                        <EditTokenDialog
                            updateUrl={update.url(editingToken.uuid)}
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
                            deleteUrl={destroy.url(selectedToken.uuid)}
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
            </SettingsLayout>
        </AppLayout>
    );
}
