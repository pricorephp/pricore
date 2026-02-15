import {
    destroy,
    store,
} from '@/actions/App/Domains/Token/Http/Controllers/UserTokenController';
import CreateTokenDialog from '@/components/create-token-dialog';
import InfoBox from '@/components/info-box';
import RevokeTokenDialog from '@/components/revoke-token-dialog';
import TokenCreatedDialog from '@/components/token-created-dialog';
import TokenList from '@/components/token-list';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
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
        title: 'Tokens',
        href: tokensIndex().url,
    },
];

export default function Tokens({ tokens, tokenCreated }: TokensPageProps) {
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium">
                                Personal Tokens
                            </h3>
                            <p className="text-muted-foreground">
                                Manage personal access tokens for Composer
                                authentication
                            </p>
                        </div>
                        <Button onClick={() => setCreateDialogOpen(true)}>
                            <Plus className="h-4 w-4" />
                            Create Token
                        </Button>
                    </div>

                    <div className="rounded-lg border bg-card px-4 py-2">
                        <TokenList tokens={tokens} onRevoke={handleRevoke} />
                    </div>

                    <InfoBox
                        title="About Personal Tokens"
                        description="Personal tokens grant access to packages across all organizations you belong to. For tokens scoped to a single organization, visit that organization's token settings."
                    />

                    <CreateTokenDialog
                        storeUrl={store.url()}
                        description="Create a personal token that grants access to packages across all your organizations."
                        isOpen={createDialogOpen}
                        onClose={() => setCreateDialogOpen(false)}
                    />

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
                            isOpen={tokenCreatedDialogOpen}
                            onClose={() => setTokenCreatedDialogOpen(false)}
                        />
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
